<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * AudioController maneja la carga y procesamiento de partituras musicales.
 * 
 * Flujo del procesamiento:
 * 1. Recibe partitura (PNG/JPG) y tipo de saxofón
 * 2. Verifica que la imagen tenga resolución suficiente
 * 3. Analiza con Audiveris para extraer notas (genera MusicXML)
 * 4. Parsea el XML y transpone notas al instrumento seleccionado
 * 5. Devuelve JSON con notas transposicionadas
 */
class AudioController extends Controller
{
    // Transposiciones en semitonos para cada tipo de saxofón
    private $instruments = [
        'alto'    => 9,
        'tenor'   => 2,
        'soprano' => 2,
    ];

    /**
     * Detecta si estamos en Windows o Linux y devuelve
     * la ruta correcta al ejecutable/jar de Audiveris.
     */
    private function getAudiverisCommand(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            // En local (Windows): usar el .exe portátil
            $exe = $_ENV['AUDIVERIS_DIR'] . DIRECTORY_SEPARATOR . 'audiveris.exe';
            return "\"{$exe}\"";
        } else {
            // En producción (Linux/Docker): usar java -cp con todos los jars
            $jar     = env('AUDIVERIS_JAR', '/var/www/html/audiveris/app/audiveris.jar');
            $libDir  = env('AUDIVERIS_DIR', '/var/www/html/audiveris/app');
            return "java -cp \"{$jar}:{$libDir}/*\" Audiveris";
        }
    }

    /**
     * Endpoint principal: procesar partitura
     * POST /procesar
     */
    public function procesar(Request $request)
    {
        // PASO 1: Validar entrada (sin PDF)
        $request->validate([
            'partitura'   => 'required|file|mimes:png,jpg,jpeg',
            'instrumento' => 'required|in:alto,tenor,soprano',
            'origen'      => 'required|in:c,alto,tenor,soprano',
        ]);

        $origen = $request->input('origen');

        // PASO 2: Guardar archivo en disco
        $archivo     = $request->file('partitura');
        $uploadDir   = storage_path('app/uploads');

        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $nombreArchivo = uniqid() . '.' . $archivo->getClientOriginalExtension();
        $archivo->move($uploadDir, $nombreArchivo);
        $fullPath = $uploadDir . DIRECTORY_SEPARATOR . $nombreArchivo;

        if (!file_exists($fullPath)) {
            return response()->json(['error' => 'El archivo no se guardó correctamente.'], 500);
        }

        // PASO 3: Verificar resolución mínima
        try {
            $this->upscaleImage($fullPath);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        // PASO 4: Procesar con Audiveris
        try {
            $resultadoXml = $this->runAudiveris($fullPath);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error en Audiveris: ' . $e->getMessage()], 500);
        }

        // PASO 5: Parsear MusicXML
        $notas = $this->parseMusicXml($resultadoXml);

        // PASO 6: Transponer notas
        $instrumento     = $request->input('instrumento');
        $notasTranspuestas = $this->transposeNotes($notas, $instrumento, $origen);

        // PASO 7: Devolver JSON
        return response()->json([
            'instrumento' => $instrumento,
            'totalNotas'  => count($notasTranspuestas),
            'notas'       => $notasTranspuestas,
            'xml'         => $resultadoXml,
        ])
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
    }

    /**
     * Verificar resolución mínima de la imagen.
     */
    private function upscaleImage($imagePath)
    {
        $imageSize = getimagesize($imagePath);
        if (!$imageSize) return;

        $maxDimension = max($imageSize[0], $imageSize[1]);

        if ($maxDimension < 400) {
            throw new \Exception("Imagen demasiado pequeña ({$maxDimension}px). Mínimo: 400px, ideal: 1500px+.");
        }

        if ($maxDimension < 1000) {
            \Log::warning("Resolución baja ({$maxDimension}px). El reconocimiento puede ser inexacto.");
        }
    }

    /**
     * Ejecutar Audiveris según el SO detectado.
     */
    private function runAudiveris($imagePath)
    {
        if (!file_exists($imagePath)) {
            throw new \Exception("Imagen no existe: {$imagePath}");
        }

        $outputDir = storage_path('audiveris_output');
        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // Obtener comando correcto según SO (Windows .exe / Linux .jar)
        $audiverisCmd = $this->getAudiverisCommand();

        $command = "{$audiverisCmd} -batch -transcribe -export -output \"{$outputDir}\" \"{$imagePath}\" 2>&1";
        $output  = shell_exec($command);

        // Buscar XML/MXL generado
        $iterator = new \RecursiveDirectoryIterator($outputDir, \RecursiveDirectoryIterator::SKIP_DOTS);
        $filter   = new \RecursiveCallbackFilterIterator($iterator, function ($current) {
            return in_array($current->getExtension(), ['xml', 'mxl']);
        });
        $files = iterator_to_array(new \RecursiveIteratorIterator($filter));

        if (empty($files)) {
            throw new \Exception("Audiveris no generó MusicXML. Output: " . substr($output, 0, 500));
        }

        return reset($files)->getPathname();
    }

    /**
     * Parsear MusicXML para extraer notas.
     */
    private function parseMusicXml($xmlPath)
    {
        if (pathinfo($xmlPath, PATHINFO_EXTENSION) === 'mxl') {
            $zip = new \ZipArchive();
            if ($zip->open($xmlPath) === true) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $name = $zip->getNameIndex($i);
                    if (pathinfo($name, PATHINFO_EXTENSION) === 'xml' && strpos($name, 'META-INF') === false) {
                        $xmlContent = $zip->getFromIndex($i);
                        $xml        = simplexml_load_string($xmlContent);
                        $zip->close();
                        break;
                    }
                }
                if (!isset($xml)) {
                    $zip->close();
                    throw new \Exception("No se encontró XML dentro del MXL.");
                }
            }
        } else {
            $xml = simplexml_load_file($xmlPath);
        }

        $notes            = [];
        $namespaces       = $xml->getNamespaces();
        $defaultNamespace = reset($namespaces) ?: null;

        $parts = $defaultNamespace ? $xml->children($defaultNamespace)->{'part'} : $xml->part;
        foreach ($parts as $part) {
            $measures = $defaultNamespace ? $part->children($defaultNamespace)->{'measure'} : $part->measure;
            foreach ($measures as $measure) {
                $measureNum   = (int)$measure['number'];
                $noteElements = $defaultNamespace ? $measure->children($defaultNamespace)->{'note'} : $measure->note;

                foreach ($noteElements as $note) {
                    if (isset($note->rest)) continue;

                    $pitchElement = $defaultNamespace ? $note->children($defaultNamespace)->{'pitch'} : $note->pitch;
                    if (isset($pitchElement[0])) {
                        $pitch    = $pitchElement[0];
                        $stepEl   = $defaultNamespace ? $pitch->children($defaultNamespace)->{'step'}   : $pitch->step;
                        $alterEl  = $defaultNamespace ? $pitch->children($defaultNamespace)->{'alter'}  : $pitch->alter;
                        $octaveEl = $defaultNamespace ? $pitch->children($defaultNamespace)->{'octave'} : $pitch->octave;
                        $typeEl   = $defaultNamespace ? $note->children($defaultNamespace)->{'type'}    : $note->type;

                        $notes[] = [
                            'measure' => $measureNum,
                            'step'    => (string)$stepEl[0],
                            'alter'   => (int)($alterEl[0] ?? 0),
                            'octave'  => (int)$octaveEl[0],
                            'type'    => (string)$typeEl[0],
                        ];
                    }
                }
            }
        }

        return $notes;
    }

    /**
     * Transponer notas según instrumento origen y destino.
     */
    private function transposeNotes($notes, $final, $origen)
    {
        $offset = [
            'c'       => 0,
            'alto'    => 9,
            'tenor'   => 2,
            'soprano' => 2,
        ];

        $semitonos = $offset[$final] - $offset[$origen];

        $midiMap  = ['C' => 0, 'D' => 2, 'E' => 4, 'F' => 5, 'G' => 7, 'A' => 9, 'B' => 11];
        $notesMap = ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B'];

        $transposed = [];

        foreach ($notes as $note) {
            $midi     = ($note['octave'] + 1) * 12 + $midiMap[$note['step']] + $note['alter'];
            $newMidi  = $midi + $semitonos;
            $newNote  = $notesMap[((($newMidi % 12) + 12) % 12)];
            $newOctave = floor($newMidi / 12) - 1;

            $transposed[] = [
                'measure'  => $note['measure'],
                'original' => $note['step'] . $note['octave'],
                'sax'      => $newNote . $newOctave,
                'type'     => $note['type'],
            ];
        }

        return $transposed;
    }
}