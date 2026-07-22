<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reconocimiento Vehicular</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f6fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            padding: 40px;
            width: 100%;
            max-width: 640px;
        }
        h1 {
            font-size: 24px;
            color: #1a1f36;
            margin-bottom: 4px;
        }
        p.sub {
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 24px;
        }
        .upload-area {
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 40px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            background: #f8fafc;
        }
        .upload-area:hover, .upload-area.dragover {
            border-color: #4f46e5;
            background: #eef2ff;
        }
        .upload-area i {
            font-size: 48px;
            color: #4f46e5;
            margin-bottom: 12px;
            display: block;
        }
        .upload-area p {
            color: #374151;
            font-size: 15px;
        }
        .upload-area small {
            color: #9ca3af;
            font-size: 12px;
        }
        #preview {
            display: none;
            max-width: 100%;
            max-height: 300px;
            border-radius: 8px;
            margin-top: 16px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            height: 40px;
            padding: 0 24px;
            background: linear-gradient(90deg, #4f46e5, #6366f1);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 16px;
            transition: opacity 0.15s;
        }
        .btn:hover { opacity: 0.9; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
        }
        .result {
            margin-top: 24px;
            display: none;
        }
        .result-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
        }
        .result-card h3 {
            font-size: 16px;
            color: #1a1f36;
            margin-bottom: 16px;
        }
        .result-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .result-item {
            background: #fff;
            border-radius: 8px;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
        }
        .result-item .label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #6b7280;
            font-weight: 600;
        }
        .result-item .value {
            font-size: 16px;
            font-weight: 700;
            color: #1a1f36;
            margin-top: 2px;
        }
        .result-item .value .suggested {
            font-weight: 400;
            font-size: 12px;
            color: #9ca3af;
        }
        .confidence-bar {
            height: 4px;
            border-radius: 2px;
            background: #e2e8f0;
            margin-top: 6px;
        }
        .confidence-bar .fill {
            height: 100%;
            border-radius: 2px;
            transition: width 0.5s;
        }
        .fill-high { background: #16a34a; }
        .fill-mid { background: #d97706; }
        .fill-low { background: #dc2626; }

        .badge-auto { color: #16a34a; font-size: 11px; font-weight: 600; }
        .badge-suggest { color: #d97706; font-size: 11px; font-weight: 600; }

        .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid #e2e8f0;
            border-top-color: #4f46e5;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
            margin: 0 auto;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        .error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 12px 16px;
            margin-top: 12px;
            font-size: 14px;
            display: none;
        }

        @media (max-width: 500px) {
            .container { padding: 20px; }
            .result-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Reconocimiento Vehicular</h1>
        <p class="sub">Sube una foto del vehículo para detectar placa, marca, modelo, color y tipo.</p>

        <!-- Upload -->
        <div class="upload-area" id="uploadArea" onclick="document.getElementById('fileInput').click()">
            <i>📸</i>
            <p>Haz clic o arrastra una imagen aquí</p>
            <small>JPG, PNG — Máx 20MB</small>
            <input type="file" id="fileInput" accept="image/*" style="display:none">
            <img id="preview" alt="Preview">
        </div>

        <div style="display:flex;gap:12px;align-items:center;justify-content:center;">
            <button class="btn" id="btnAnalyze" disabled onclick="analyze()">🔎 Analizar</button>
            <div class="spinner" id="spinner"></div>
        </div>

        <div class="error" id="error"></div>

        <!-- Result -->
        <div class="result" id="result">
            <div class="result-card">
                <h3>📋 Resultados</h3>
                <div class="result-grid" id="resultGrid"></div>
            </div>

            <div style="margin-top:12px;padding:12px 16px;background:#fffbeb;border-radius:8px;border:1px solid #fde68a;font-size:13px;color:#92400e;" id="warnings"></div>
        </div>
    </div>

    <script>
        const fileInput = document.getElementById('fileInput');
        const preview = document.getElementById('preview');
        const uploadArea = document.getElementById('uploadArea');
        const btnAnalyze = document.getElementById('btnAnalyze');

        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(ev) {
                    preview.src = ev.target.result;
                    preview.style.display = 'block';
                    uploadArea.querySelector('p').textContent = file.name;
                    uploadArea.querySelector('small').textContent = (file.size / 1024 / 1024).toFixed(1) + ' MB';
                    btnAnalyze.disabled = false;
                };
                reader.readAsDataURL(file);
            }
        });

        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });
        uploadArea.addEventListener('dragleave', function() {
            this.classList.remove('dragover');
        });
        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            if (e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                fileInput.dispatchEvent(new Event('change'));
            }
        });

        async function analyze() {
            const file = fileInput.files[0];
            if (!file) return;

            const btn = document.getElementById('btnAnalyze');
            const spinner = document.getElementById('spinner');
            const error = document.getElementById('error');
            const result = document.getElementById('result');
            const warnings = document.getElementById('warnings');

            btn.disabled = true;
            spinner.style.display = 'block';
            error.style.display = 'none';
            result.style.display = 'none';
            warnings.innerHTML = '';

            const formData = new FormData();
            formData.append('image', file);

            try {
                const res = await fetch('api/recognize.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (!data.success) {
                    error.textContent = data.message || 'Error al procesar la imagen';
                    error.style.display = 'block';
                    return;
                }

                renderResults(data);
            } catch (err) {
                error.textContent = 'Error de conexión: ' + err.message;
                error.style.display = 'block';
            } finally {
                btn.disabled = false;
                spinner.style.display = 'none';
            }
        }

        function renderResults(data) {
            const grid = document.getElementById('resultGrid');
            const warnings = document.getElementById('warnings');
            const result = document.getElementById('result');
            let warns = [];

            const fields = [
                { key: 'plate', label: 'Placa', icon: '🪪' },
                { key: 'brand', label: 'Marca', icon: '🏭' },
                { key: 'model', label: 'Modelo', icon: '🚗' },
                { key: 'color', label: 'Color', icon: '🎨' },
                { key: 'vehicleType', label: 'Tipo', icon: '🚙' }
            ];

            grid.innerHTML = '';
            fields.forEach(f => {
                const item = data[f.key] || {};
                const val = item.value || '—';
                const conf = item.confidence || 0;
                const autoComplete = item.autoComplete !== false;

                const confClass = conf >= 0.8 ? 'fill-high' : conf >= 0.7 ? 'fill-mid' : 'fill-low';
                const badge = autoComplete
                    ? '<span class="badge-auto">✅ Auto</span>'
                    : '<span class="badge-suggest">💡 Sugerencia</span>';

                if (!autoComplete) {
                    warns.push(`<b>${f.label}:</b> confianza baja (${(conf*100).toFixed(0)}%), se muestra como sugerencia`);
                }

                const html = `
                    <div class="result-item">
                        <div class="label">${f.icon} ${f.label} ${badge}</div>
                        <div class="value">${val} <span class="suggested">(${(conf*100).toFixed(0)}%)</span></div>
                        <div class="confidence-bar"><div class="fill ${confClass}" style="width:${conf*100}%"></div></div>
                    </div>
                `;
                grid.innerHTML += html;
            });

            warnings.innerHTML = warns.length
                ? '⚠️ ' + warns.join('<br>')
                : '✅ Todos los campos con alta confianza, se auto-completaron correctamente.';

            result.style.display = 'block';
        }
    </script>
</body>
</html>
