<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div class="wrap">
    <h1>Editor de Datos de Entrenamiento</h1>
    
    <?php settings_errors(); ?>
    
    <div class="cerebroly-editor-container">
        <p class="description">
            Edita el JSON de entrenamiento directamente. Cada elemento representa un par de pregunta-respuesta para el entrenamiento.
            <strong>Nota:</strong> Los cambios realizados aquí afectarán al entrenamiento del modelo. Asegúrate de que el formato sea correcto.
        </p>
        
        <form method="post" action="" id="training-form">
            <?php wp_nonce_field('cerebroly_update_training'); ?>
            
            <div class="cerebroly-editor-actions">
                <button type="button" id="cerebroly-add-entry" class="button">Añadir Nuevo Par</button>
                <button type="button" id="cerebroly-format-json" class="button">Formatear JSON</button>
                <span class="status-indicator">Editor: <span id="editor-status">Listo</span></span>
            </div>
            
            <!-- Contenedor para el editor Monaco -->
            <div id="monaco-editor" style="width: 100%; height: 500px; border: 1px solid #ddd;"></div>
            
            <!-- Campo oculto para enviar el valor - ahora con entity_decode para manejar caracteres -->
            <textarea name="cerebroly_training_data" id="cerebroly-json-value" style="display: none;"><?php echo esc_textarea($training_json_pretty); ?></textarea>
            
            <div class="cerebroly-validation-message"></div>
            
            <div class="cerebroly-editor-actions">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="Guardar Cambios">
                <a href="<?php echo esc_url(admin_url('admin.php?page=cerebroly-training-preview')); ?>" class="button">Ver Vista Previa</a>
<a href="<?php echo esc_url(admin_url('admin-post.php?action=cerebroly_start_training')); ?>" class="button button-secondary" onclick="return confirm('¿Estás seguro de que deseas iniciar el entrenamiento con estos datos?');">Iniciar Entrenamiento</a>
            </div>
        </form>
    </div>
    
    <div class="cerebroly-editor-help">
        <h3>Formato JSON</h3>
        <p>Cada entrada debe seguir este formato:</p>
        <pre>{
  "messages": [
    {
      "role": "user",
      "content": "¿Pregunta del usuario?"
    },
    {
      "role": "assistant",
      "content": "Respuesta del asistente."
    }
  ]
}</pre>
        <h3>Consejos</h3>
        <ul>
            <li>Asegúrate de que cada pregunta sea específica y relevante para tu contenido.</li>
            <li>Las respuestas deben ser útiles y precisas, basadas en la información real de tu sitio.</li>
            <li>Puedes añadir múltiples pares de preguntas y respuestas para cada tema importante.</li>
            <li>Incluye variaciones de preguntas comunes para mejorar el entrenamiento.</li>
        </ul>
    </div>
</div>

<style>
    .cerebroly-editor-container {
        background: #fff;
        border: 1px solid #ccd0d4;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .cerebroly-editor-actions {
        margin: 15px 0;
        display: flex;
        gap: 10px;
        align-items: center;
    }
    
    .status-indicator {
        margin-left: auto;
        padding: 5px 10px;
        background: #f0f0f0;
        border-radius: 3px;
        font-size: 12px;
    }
    
    #editor-status.saving {
        color: #0073aa;
    }
    
    #editor-status.error {
        color: #dc3232;
    }
    
    #editor-status.success {
        color: #46b450;
    }
    
    .cerebroly-validation-message {
        padding: 10px;
        margin: 10px 0;
        display: none;
    }
    
    .cerebroly-validation-success {
        background-color: #f0fff0;
        border-left: 4px solid #46b450;
        display: block;
    }
    
    .cerebroly-validation-error {
        background-color: #fff0f0;
        border-left: 4px solid #dc3232;
        display: block;
    }
    
    .cerebroly-editor-help {
        background: #f9f9f9;
        border: 1px solid #ccd0d4;
        padding: 15px;
    }
    
    .cerebroly-editor-help pre {
        background: #f1f1f1;
        padding: 10px;
        overflow-x: auto;
        border: 1px solid #ddd;
    }
    
    .cerebroly-editor-help ul {
        list-style-type: disc;
        margin-left: 20px;
    }
</style>

<?php 
wp_enqueue_script(
    'monaco-editor-loader',
    CEREBROLY_PLUGIN_URL . 'assets/js/libs/loader.min.js',
    array(),
    '0.36.1',
    true
);

?>

<script>

jQuery(document).ready(function($) {
    // Prevenir múltiples cargas de Monaco
    if (window.monacoLoaded) return;
    window.monacoLoaded = true;

    let editor;
    
    // Configurar el cargador de Monaco de manera más segura
    if (typeof require !== 'undefined') {
        require.config({ 
            paths: { 
                'vs': 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.36.1/min/vs'
            },
            catchError: true
        });

        require(['vs/editor/editor.main'], function() {
            try {
                // Definir esquema JSON para validación
                monaco.languages.json.jsonDefaults.setDiagnosticsOptions({
                    validate: true,
                    schemas: [{
                        uri: "http://myschema/training-data.json",
                        fileMatch: ["*"],
                        schema: {
                            type: "array",
                            items: {
                                type: "object",
                                properties: {
                                    messages: {
                                        type: "array",
                                        items: {
                                            type: "object",
                                            properties: {
                                                role: {
                                                    type: "string",
                                                    enum: ["user", "assistant"]
                                                },
                                                content: {
                                                    type: "string"
                                                }
                                            },
                                            required: ["role", "content"]
                                        },
                                        minItems: 2
                                    }
                                },
                                required: ["messages"]
                            }
                        }
                    }]
                });
                
                // Inicializar el editor
                editor = monaco.editor.create(document.getElementById('monaco-editor'), {
                    value: $('#cerebroly-json-value').val(),
                    language: 'json',
                    theme: 'vs-dark',
                    automaticLayout: true,
                    minimap: {
                        enabled: true
                    },
                    formatOnPaste: true,
                    formatOnType: true,
                    scrollBeyondLastLine: false,
                    wordWrap: 'on'
                });
                
                // Manejar cambios en el editor
                editor.onDidChangeModelContent(function() {
                    $('#cerebroly-json-value').val(editor.getValue());
                    $('#editor-status').text('Modificado').removeClass('saving success error');
                });

                // Añadir botón de mejora con IA
                const enhanceButton = $(`
                    <button type="button" class="button button-secondary" id="cerebroly-generate-dataset" style="margin-left: 10px;">
                        🤖 Mejorar con IA
                    </button>
                `);

                // Insertar botón al lado de los otros botones de acción
                $('.cerebroly-editor-actions').append(enhanceButton);

            } catch (error) {
                console.error('Error inicializando Monaco Editor:', error);
            }
        });
    }

    // Función para iniciar mejora del dataset
    function startDatasetEnhancement() {
        const progressModal = $(`
            <div id="cerebroly-enhancement-progress-modal" class="cerebroly-modal" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); display:flex; justify-content:center; align-items:center; z-index:10000;">
                <div style="background:white; padding:20px; border-radius:5px; width:600px; max-width:90%; max-height:80%; overflow-y:auto;">
                    <h2>Mejorando Dataset con IA</h2>
                    
                    <div style="background:#f8f8f8; padding:15px; margin-bottom:15px; border-radius:4px;">
                        <div style="height:20px; background:#e9ecef; border-radius:4px; margin-bottom:10px;">
                            <div id="cerebroly-progress-bar" style="height:100%; background-color:#28a745; width:0%; border-radius:4px; transition:width 0.5s ease;"></div>
                        </div>
                        <div style="display:flex; justify-content:space-between;">
                            <span id="cerebroly-progress-text">Preparando mejora...</span>
                            <span id="cerebroly-progress-percent">0%</span>
                        </div>
                    </div>
                    
                    <div id="cerebroly-log-container" style="max-height:300px; overflow-y:auto; background:#f4f4f4; padding:10px; border-radius:4px;">
                        <div id="cerebroly-log-entries"></div>
                    </div>
                    
                    <div style="margin-top:15px; text-align:right;">
                        <button id="cerebroly-cancel-enhancement" class="button">Cancelar Proceso</button>
                    </div>
                </div>
            </div>
        `);

        $('body').append(progressModal);

        let currentIndex = 0;
        let enhancedItems = [];
        const batchSize = 5;
        let isCancelled = false;
        let totalItems = 0;

        function updateProgress(processed, total) {
            const progressPercent = Math.floor((processed / total) * 100);
            $('#cerebroly-progress-bar').css('width', `${progressPercent}%`);
            $('#cerebroly-progress-text').text(`Procesados ${processed} de ${total} elementos`);
            $('#cerebroly-progress-percent').text(`${progressPercent}%`);
        }

        function addLogEntry(message, type = 'info') {
            console.log(`Log [${type}]: ${message}`);
            const logEntry = $(`<p style="margin:5px 0; color:${
                type === 'success' ? '#28a745' : 
                type === 'error' ? '#dc3232' : 
                '#0073a7'
            };">${message}</p>`);
            $('#cerebroly-log-entries').append(logEntry);
            
            const logContainer = $('#cerebroly-log-container')[0];
            logContainer.scrollTop = logContainer.scrollHeight;
        }

        function processBatch() {
            if (isCancelled) {
                addLogEntry('Proceso cancelado por el usuario.', 'error');
                $('#cerebroly-cancel-enhancement').prop('disabled', true);
                return;
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'cerebroly_generate_enhanced_dataset',
                    security: '<?php echo esc_js(wp_create_nonce('cerebroly_generate_dataset')); ?>',
                    batch_size: batchSize,
                    start_index: currentIndex
                },
                dataType: 'json',
                success: function(response) {
                    console.log('Respuesta del servidor:', response);

                    if (response.success) {
                        // Establecer total de elementos en el primer lote
                        if (currentIndex === 0) {
                            totalItems = response.data.total_items;
                            addLogEntry(`Total de elementos a procesar: ${totalItems}`, 'info');
                        }

                        enhancedItems = enhancedItems.concat(response.data.enhanced_items);
                        
                        currentIndex = response.data.next_index;
                        updateProgress(currentIndex, totalItems);
                        
                        addLogEntry(`Procesados ${response.data.total_processed} elementos`, 'success');
                        
                        if (!response.data.is_completed) {
                            processBatch();
                        } else {
                            finalizeEnhancement();
                        }
                    } else {
                        addLogEntry(`Error: ${response.data}`, 'error');
                        isCancelled = true;
                        $('#cerebroly-cancel-enhancement').prop('disabled', true);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error AJAX:', status, error);
                    console.log('Respuesta del servidor:', xhr.responseText);
                    
                    addLogEntry(`Error de conexión: ${error}`, 'error');
                    isCancelled = true;
                    $('#cerebroly-cancel-enhancement').prop('disabled', true);
                }
            });
        }

        // In the finalizeEnhancement() function in training-editor.php

function finalizeEnhancement() {
    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'cerebroly_finalize_enhanced_dataset',
            security: '<?php echo esc_js(wp_create_nonce('cerebroly_generate_dataset')); ?>',
            enhanced_dataset: JSON.stringify(enhancedItems)
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                addLogEntry(`Dataset mejorado guardado. Nuevas entradas: ${response.data.count}`, 'success');
                
                // Update both the Monaco editor and the hidden textarea
                if (editor) {
                    const formattedJson = JSON.stringify(enhancedItems, null, 2);
                    editor.setValue(formattedJson);
                    
                    // IMPORTANT: Also update the hidden textarea that holds the form value
                    $('#cerebroly-json-value').val(formattedJson);
                    
                    // Update status indicator
                    $('#editor-status').text('Actualizado').addClass('success').removeClass('saving error');
                    
                    // Make sure the validation message is updated
                    $('.cerebroly-validation-message').html('El dataset ha sido mejorado y actualizado correctamente.')
                        .addClass('cerebroly-validation-success')
                        .removeClass('cerebroly-validation-error')
                        .show();
                }
                
                // Add a slight delay before closing the modal
                setTimeout(() => {
                    progressModal.remove();
                    
                    // Optional: Show a confirmation dialog
                    alert('El dataset ha sido mejorado correctamente. Para guardar los cambios permanentemente, haz clic en "Guardar Cambios".');
                }, 2000);
            } else {
                addLogEntry(`Error al guardar: ${response.data}`, 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error finalizando:', status, error);
            addLogEntry(`Error de conexión al guardar: ${error}`, 'error');
        }
    });
}

        // Manejar cancelación
        $('#cerebroly-cancel-enhancement').on('click', function() {
            if (confirm('¿Estás seguro de que deseas cancelar el proceso de mejora?')) {
                isCancelled = true;
                $(this).prop('disabled', true);
                addLogEntry('Cancelando proceso...', 'info');
            }
        });

        // Iniciar procesamiento
        processBatch();
    }

    // Evento para el botón de mejora con IA
    $(document).on('click', '#cerebroly-generate-dataset', function() {
        // Modal de confirmación
        const confirmModal = $(`
            <div id="cerebroly-enhancement-confirm-modal" class="cerebroly-modal" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); display:flex; justify-content:center; align-items:center; z-index:10000;">
                <div style="background:white; padding:20px; border-radius:5px; width:500px; max-width:90%;">
                    <h2>Mejorar Dataset con IA</h2>
                    <div style="background:#f8f8f8; padding:15px; margin:10px 0; border-radius:4px;">
                        <p>⚠️ Este proceso enviará el contenido de tu sitio web a OpenAI para generar variaciones.</p>
                        <p><strong>Advertencia:</strong> No se utilizará el JSON actual, sino el contenido original de WordPress.</p>
                        <p>¿Deseas continuar?</p>
                    </div>
                    <div style="display:flex; justify-content:flex-end; gap:10px;">
                        <button id="cerebroly-confirm-enhancement" class="button button-primary">Iniciar Mejora</button>
                        <button id="cerebroly-cancel-modal" class="button">Cancelar</button>
                    </div>
                </div>
            </div>
        `);

        $('body').append(confirmModal);

        $('#cerebroly-confirm-enhancement').on('click', function() {
            confirmModal.remove();
            startDatasetEnhancement();
        });

        $('#cerebroly-cancel-modal').on('click', function() {
            confirmModal.remove();
        });
    });
});
</script>