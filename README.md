# Cerebroly — Plugin de IA para WordPress con RAG y fine-tuning

Convierte cualquier WordPress en un agente conversacional impulsado por OpenAI. Entrena la IA con tu propio contenido mediante RAG o fine-tuning, y despliega el chat en tu sitio o en dominios externos.

## Qué hace

Ofrece dos modos de IA, configurables en los ajustes:

- **RAG (Retrieval-Augmented Generation)** — indexa entradas, páginas, productos WooCommerce y archivos subidos en una base de datos vectorial. Cada consulta recupera los fragmentos más relevantes y los pasa al modelo para generar una respuesta fundamentada en el contenido. No requiere reentrenamiento al actualizar contenido.
- **Fine-tuning** — genera un dataset JSONL a partir del contenido del sitio, lo sube a OpenAI y crea un modelo personalizado. Requiere reentrenamiento tras cambios significativos en el contenido.

Otras características:
- Widget de chat embebible vía shortcode o iframe externo
- 3 temas visuales incluidos (claro, oscuro, corporativo)
- Limitación de peticiones por minuto (rate limiting)
- Lista blanca de dominios CORS
- REST API
- Traducciones incluidas: EN, ES, FR, PT

---

## Requisitos

| Requisito | Mínimo |
|-----------|--------|
| WordPress | 5.8 |
| PHP | 8.0 |
| Clave de API de OpenAI | Obligatoria |

---

## Instalación

1. Sube la carpeta `cerebroly/` a `/wp-content/plugins/`.
2. Activa el plugin desde **Plugins → Plugins instalados**.
3. En **Cerebroly → Ajustes**, introduce tu clave de API de OpenAI.
4. Elige el modo (RAG o Fine-tuning) y configúralo.
5. Añade `[cerebroly_chat]` en cualquier página o entrada.

---

## Páginas del plugin

| Página | Qué hace |
|--------|----------|
| **Dashboard** | Estado general del sistema: modo activo (RAG o Fine-tuning), estado de la conexión con OpenAI, estadísticas del contenido indexado o del dataset de entrenamiento, modelos fine-tuned disponibles, diagnóstico de API y de cron, shortcode listo para copiar. |
| **Ajustes** | Clave de API de OpenAI, activar/desactivar modo RAG, lista de dominios permitidos (CORS), activar CORS, configuración de rate limiting (límite de peticiones por minuto). |
| **Sistema RAG** | Configura la indexación (modelo de embeddings, tamaño de chunk, solapamiento, tipos de contenido), los parámetros de recuperación (Top K, umbral de similitud, método de búsqueda, reescritura de consulta) y la generación (modelo LLM, temperatura, tokens, prompt de sistema, citar fuentes). Botón para iniciar la indexación. |
| **Fine-Tuning** | Genera y previsualiza el dataset JSONL, inicia el entrenamiento en OpenAI, consulta el estado de los trabajos y activa el modelo entrenado. Incluye el editor de datos de entrenamiento con Monaco. |
| **Apariencia** | Selección de tema visual, icono personalizado del bot, posición del widget, tamaño, mensajes de bienvenida y mensaje de error. |
| **Archivos** | Sube y gestiona archivos adicionales de entrenamiento (TXT) que se incluyen en el dataset junto con el contenido del sitio. |

---

## Clave de API de OpenAI

El plugin resuelve la clave en este orden de prioridad:

1. Variable de entorno `OPENAI_API_KEY`
2. Constante en `wp-config.php`: `define('OPENAI_API_KEY', '...')`
3. Opción guardada en la base de datos (introducida desde Ajustes)

Si la clave se define vía entorno o constante, la opción de la base de datos se borra automáticamente.

---

## Modo RAG

Activable desde **Cerebroly → Ajustes** (opción "Activar RAG"). Configuración en **Cerebroly → Sistema RAG**:

| Sección | Opciones |
|---------|----------|
| Indexación | Modelo de embeddings (por defecto `text-embedding-ada-002`), tamaño de chunk (1000), solapamiento (200), tipos de contenido a indexar (entradas, páginas, productos, archivos). |
| Recuperación | Top K (5), umbral de similitud (0.75), método (`cosine` o `keyword`), reescritura de consulta. |
| Generación | Modelo LLM (por defecto `gpt-3.5-turbo`), temperatura (0.3), tokens máximos (1000), prompt de sistema, citar fuentes. |

Pulsa **Iniciar indexación** para construir la base vectorial. Re-indexa tras cambios importantes en el contenido.

---

## Modo Fine-tuning

Se usa cuando RAG está desactivado. En **Cerebroly → Fine-Tuning**:

1. **Extraer y previsualizar** — genera el dataset JSONL desde el contenido del sitio.
2. **Iniciar entrenamiento** — sube el dataset y lanza el trabajo en OpenAI.
3. **Estado del modelo** — modelos activos/pendientes/fallidos. Marca uno como activo para usarlo en el chat.
4. **Subir archivos** — añade archivos adicionales (TXT).

El plugin consulta el estado del trabajo automáticamente vía cron de WordPress.

### Editor de datos de entrenamiento (Monaco)

El editor está disponible dentro de **Fine-Tuning**. Usa el mismo motor que Visual Studio Code (Monaco Editor v0.36) con tema oscuro y validación JSON en tiempo real.

Cada entrada del dataset sigue este formato:

```json
{
  "messages": [
    { "role": "user",      "content": "¿Pregunta del usuario?" },
    { "role": "assistant", "content": "Respuesta del asistente." }
  ]
}
```

El editor valida el esquema automáticamente: cada objeto debe tener `messages` con al menos un turno `user` y uno `assistant`. Los roles aceptados son `"user"` y `"assistant"`.

**Acciones disponibles:**
- **Añadir Nuevo Par** — inserta una entrada vacía en el JSON.
- **Formatear JSON** — reindenta y ordena el contenido.
- **Mejorar con IA** — envía el contenido del sitio a OpenAI para generar variaciones automáticas del dataset en lotes de 5 entradas, con barra de progreso y opción de cancelar.
- **Guardar Cambios** — persiste el JSON editado en la base de datos.
- **Iniciar Entrenamiento** — lanza el trabajo de fine-tuning directamente desde el editor con el dataset guardado.

---

## Apariencia

En **Cerebroly → Apariencia** se configuran tema, icono personalizado, posición, tamaño (`default`, `medium`, `fullscreen`), mensajes de bienvenida y mensaje de error.

Temas incluidos:
- **Cerebroly Theme** — azul/blanco
- **Dark Theme** — oscuro
- **SienteGrowth Theme** — corporativo naranja

---

## Shortcode

```
[cerebroly_chat placeholder="Pregúntame lo que quieras..." button_text="Enviar"]
```

| Atributo | Por defecto |
|----------|-------------|
| `placeholder` | `Type your question...` |
| `button_text` | `Send` |

---

## Integración en sitios externos

1. Añade el dominio externo a la lista de dominios permitidos y activa CORS en Ajustes.
2. En el HTML del sitio externo:

```html
<script src="https://tu-sitio-wp.com/wp-json/cerebroly/v1/chat-embed"></script>
```
---

## Modo desarrollo

Define en `wp-config.php`:

```php
define('cerebroly_DEVELOPMENT', true);
```

Carga los archivos JS/CSS fuente individuales en lugar del bundle compilado de `dist/`.

---

## Solución de problemas

- **El widget no aparece** — comprueba que el modo (RAG o Fine-tuning) está configurado y activo, y que el shortcode está en la página.
- **Error "No active model"** — en RAG, ejecuta la indexación; en Fine-tuning, espera a que termine el entrenamiento y marca el modelo como activo.
- **El iframe externo no carga** — confirma que el dominio está en la lista de permitidos y que CORS está activado.
- **Las traducciones no cargan** — verifica que los `.mo` están en `languages/` con el patrón `cerebroly-{locale}.mo`.
- **La clave API no se guarda** — si está definida vía entorno o constante, el campo se limpia intencionadamente.

---

## Licencia

AGPL-3.0-or-later. Ver [LICENSE](LICENSE) para el texto completo.

---

## Autor

[cerebroly.com](https://cerebroly.com)
