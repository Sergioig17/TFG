<template>
  <div class="formulario-partituras">
    <form @submit.prevent="enviarFormulario" class="form-container">
      <div class="form-group">
        <label for="partitura">Selecciona una partitura:</label>
        <input
          id="partitura"
          type="file"
          accept=".pdf,.png,.jpg,.jpeg"
          @change="handleFileChange"
          required
          class="file-input"
        />
        <small>Formatos aceptados: PDF, PNG, JPG</small>
        <div v-if="archivo" class="archivo-seleccionado">
          ✓ {{ archivo.name }}
        </div>
      </div>

      <div class="form-group">
        <label for="instrumento">Tipo de saxofón:</label>
        <select
          id="instrumento"
          v-model="instrumento"
          required
          class="select-input"
        >
          <option value="">-- Selecciona un instrumento --</option>
          <option value="alto">Saxofón Alto (Eb)</option>
          <option value="tenor">Saxofón Tenor (Bb)</option>
          <option value="soprano">Saxofón Soprano (Bb)</option>
        </select>
      </div>

      <div class="form-group">
        <label for="origen">Partitura escrita para:</label>
        <select
          id="origen"
          v-model="origen"
          required
          class="select-input"
        >
          <option value="c">Concierto (C)</option>
          <option value="alto">SaxofÃ³n Alto (Eb)</option>
          <option value="tenor">SaxofÃ³n Tenor (Bb)</option>
          <option value="soprano">SaxofÃ³n Soprano (Bb)</option>
        </select>
      </div>

      <button
        type="submit"
        :disabled="cargando || !archivo || !origen || !instrumento"
        class="btn-submit"
      >
        <span v-if="cargando">Procesando...</span>
        <span v-else>Procesar Partitura</span>
      </button>
    </form>

    <!-- Mostrar errores -->
    <div v-if="error" class="error-message">
      <strong>Error:</strong> {{ error }}
    </div>

    <!-- Mostrar resultados -->
    <div v-if="notas.length > 0" class="resultados">
      <h3>Notas Transposicionadas</h3>
      <div class="notas-lista">
        <div v-for="(nota, index) in notas" :key="index" class="nota-item">
          <span class="nota-numero">{{ index + 1 }}.</span>
          <span class="nota-nombre">{{ nota.isRest ? 'Rest' : nota.transposed }}</span>
          <span class="nota-duracion">({{ nota.type }})</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'FormularioPartituras',
  created() {
    this.apiBaseUrl = (import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000').replace(/\/$/, '');
  },
  data() {
    return {
      archivo: null,
      origen: 'concierto',
      instrumento: '',
      cargando: false,
      error: '',
      notas: [],
      apiBaseUrl: ''
    };
  },
  methods: {
    handleFileChange(event) {
      this.archivo = event.target.files[0];
      this.error = '';
      this.notas = [];
    },
    async enviarFormulario() {
      if (!this.archivo || !this.origen || !this.instrumento) {
        this.error = 'Por favor completa todos los campos';
        return;
      }

      this.cargando = true;
      this.error = '';
      this.notas = [];

      try {
        const formData = new FormData();
        formData.append('partitura', this.archivo);
        formData.append('origen', this.origen);
        formData.append('instrumento', this.instrumento);

        const response = await fetch(`${this.apiBaseUrl}/api/procesar`, {
          method: 'POST',
          body: formData
        });
        if (!response.ok) {
          const errorData = await response.json();
          throw new Error(errorData.message || `Error ${response.status}`);
        }

        const data = await response.json();
        this.notas = Array.isArray(data.notas) ? data.notas : [];
        if (this.notas.length === 0) {
          this.error = 'No se encontraron notas en la partitura';
        }
      } catch (err) {
        this.error = err.message || 'Error al procesar la partitura';
      } finally {
        this.cargando = false;
      }
    }
  }
};
</script>

<style scoped>
.formulario-partituras {
  max-width: 600px;
  margin: 20px auto;
  padding: 20px;
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  background: #f9f9f9;
  font-family: Arial, sans-serif;
}

.form-container {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.form-group label {
  font-weight: bold;
  color: #333;
}

.file-input,
.select-input {
  padding: 10px;
  border: 1px solid #ccc;
  border-radius: 4px;
  font-size: 14px;
}

.file-input:focus,
.select-input:focus {
  outline: none;
  border-color: #4CAF50;
  box-shadow: 0 0 5px rgba(76, 175, 80, 0.3);
}

.form-group small {
  color: #666;
  font-size: 12px;
}

.archivo-seleccionado {
  color: #4CAF50;
  font-size: 14px;
  margin-top: 5px;
}

.btn-submit {
  padding: 12px;
  background-color: #4CAF50;
  color: white;
  border: none;
  border-radius: 4px;
  font-size: 16px;
  font-weight: bold;
  cursor: pointer;
  transition: background-color 0.3s;
}

.btn-submit:hover:not(:disabled) {
  background-color: #45a049;
}

.btn-submit:disabled {
  background-color: #ccc;
  cursor: not-allowed;
}

.error-message {
  margin-top: 20px;
  padding: 12px;
  background-color: #ffebee;
  color: #c62828;
  border-left: 4px solid #c62828;
  border-radius: 4px;
}

.resultados {
  margin-top: 30px;
  padding: 20px;
  background-color: #fff;
  border: 1px solid #e0e0e0;
  border-radius: 8px;
}

.resultados h3 {
  margin-top: 0;
  color: #333;
}

.notas-lista {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
  gap: 10px;
}

.nota-item {
  padding: 10px;
  background-color: #f0f0f0;
  border-radius: 4px;
  border-left: 4px solid #4CAF50;
  display: flex;
  align-items: center;
  gap: 8px;
}

.nota-numero {
  font-weight: bold;
  color: #666;
}

.nota-nombre {
  font-weight: bold;
  color: #333;
  font-size: 18px;
}

.nota-duracion {
  font-size: 12px;
  color: #999;
}
</style>
