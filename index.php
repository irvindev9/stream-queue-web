<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stream Grabador - Cola de Descargas</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bg-color: #0b0f19;
            --card-bg: rgba(17, 24, 39, 0.7);
            --border-color: rgba(255, 255, 255, 0.08);
            --accent-color: #8b5cf6;
            --accent-hover: #a78bfa;
            --text-main: #f3f4f6;
            --text-muted: #9ca3af;
            
            /* Status colors */
            --status-pending-bg: rgba(234, 179, 8, 0.15);
            --status-pending-text: #facc15;
            --status-recording-bg: rgba(59, 130, 246, 0.15);
            --status-recording-text: #60a5fa;
            --status-completed-bg: rgba(34, 197, 94, 0.15);
            --status-completed-text: #4ade80;
            --status-failed-bg: rgba(239, 68, 68, 0.15);
            --status-failed-text: #f87171;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem 1rem;
            overflow-x: hidden;
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(139, 92, 246, 0.08) 0%, transparent 40%),
                radial-gradient(circle at 90% 80%, rgba(59, 130, 246, 0.08) 0%, transparent 40%);
        }

        .container {
            width: 100%;
            max-width: 800px;
            display: flex;
            flex-direction: column;
            gap: 2rem;
            animation: fadeIn 0.8s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        header {
            text-align: center;
        }

        h1 {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #fff 30%, #a78bfa 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
            letter-spacing: -0.02em;
        }

        header p {
            color: var(--text-muted);
            font-size: 1rem;
            font-weight: 300;
        }

        /* Card container with Glassmorphism */
        .card {
            background: var(--card-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            position: relative;
        }

        /* Form */
        form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        @media (min-width: 600px) {
            form {
                flex-direction: row;
                align-items: stretch;
            }
        }

        .input-group {
            flex-grow: 1;
            position: relative;
        }

        input[type="url"] {
            width: 100%;
            padding: 1rem 1.2rem;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: #fff;
            font-family: inherit;
            font-size: 1rem;
            outline: none;
            transition: all 0.3s ease;
        }

        input[type="url"]:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.25);
            background: rgba(0, 0, 0, 0.3);
        }

        button {
            padding: 1rem 2rem;
            background: var(--accent-color);
            color: white;
            border: none;
            border-radius: 12px;
            font-family: inherit;
            font-size: 1.05rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.25);
        }

        button:hover {
            background: var(--accent-hover);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(139, 92, 246, 0.4);
        }

        button:active {
            transform: translateY(0);
        }

        button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* Queue List */
        .queue-container {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .queue-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 0.75rem;
        }

        .queue-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .clear-btn {
            background: transparent;
            color: var(--text-muted);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            font-size: 0.85rem;
            box-shadow: none;
        }

        .clear-btn:hover {
            background: rgba(239, 68, 68, 0.1);
            color: #f87171;
            border-color: rgba(239, 68, 68, 0.2);
            box-shadow: none;
        }

        .table-wrapper {
            overflow-x: auto;
            border-radius: 12px;
            background: rgba(0, 0, 0, 0.15);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.95rem;
        }

        th, td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        th {
            background: rgba(255, 255, 255, 0.02);
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .url-cell {
            max-width: 250px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: #e5e7eb;
            font-family: monospace;
        }

        /* Status Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }

        .badge-pending {
            background: var(--status-pending-bg);
            color: var(--status-pending-text);
        }

        .badge-recording {
            background: var(--status-recording-bg);
            color: var(--status-recording-text);
            animation: pulse 2s infinite ease-in-out;
        }

        .badge-completed {
            background: var(--status-completed-bg);
            color: var(--status-completed-text);
        }

        .badge-failed {
            background: var(--status-failed-bg);
            color: var(--status-failed-text);
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }

        .date-cell {
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-muted);
            font-weight: 300;
        }

        /* Toast Notifications */
        .toast {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            background: #1e1b4b;
            border: 1px solid var(--accent-color);
            color: #fff;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            z-index: 1000;
        }

        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }

        .toast.error {
            background: #451a1a;
            border-color: #ef4444;
        }

        /* Spinner for Button */
        .spinner {
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top: 2px solid #fff;
            width: 16px;
            height: 16px;
            animation: spin 1s linear infinite;
            display: none;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Priority Styles */
        .priority-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 0.5rem 0.8rem;
        }

        .priority-group label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
        }

        .priority-group input[type="number"] {
            width: 45px;
            background: transparent;
            border: none;
            color: #fff;
            font-family: inherit;
            font-size: 1.1rem;
            font-weight: 700;
            text-align: center;
            outline: none;
        }

        .priority-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.25rem 0.65rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        .priority-1 {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.4);
        }

        .priority-2 {
            background: rgba(245, 158, 11, 0.2);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.4);
        }

        .priority-3 {
            background: rgba(156, 163, 175, 0.15);
            color: #9ca3af;
            border: 1px solid rgba(156, 163, 175, 0.3);
        }

        .priority-select {
            appearance: none;
            -webkit-appearance: none;
            padding: 0.3rem 0.65rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            outline: none;
            font-family: inherit;
            transition: all 0.2s ease;
        }

        .priority-select:hover {
            filter: brightness(1.2);
        }

        .priority-select option {
            background: #111827;
            color: #f3f4f6;
        }
    </style>
</head>
<body>

    <div class="container">
        <header>
            <h1>Stream Downloader</h1>
            <p>Agregar enlaces a la cola de grabación remota</p>
        </header>

        <div class="card">
            <form id="add-form">
                <div class="input-group">
                    <input 
                        type="url" 
                        id="stream-url" 
                        name="url" 
                        placeholder="Pegar enlace del stream aquí... (https://...)" 
                        required
                    >
                </div>
                <button type="submit" id="submit-btn">
                    <span class="spinner" id="btn-spinner"></span>
                    <span>Agregar a la Cola</span>
                </button>
            </form>

            <div class="queue-container">
                <div class="queue-header">
                    <h2>Cola Reciente</h2>
                    <button class="clear-btn" id="clear-btn">Limpiar Historial</button>
                </div>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Enlace</th>
                                <th>Estado</th>
                                <th>Fecha de Registro</th>
                            </tr>
                        </thead>
                        <tbody id="queue-body">
                            <tr>
                                <td colspan="4" class="empty-state">Cargando cola...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="queue-container" style="margin-top: 1rem;">
                <div class="queue-header">
                    <h2>Estado de Clientes</h2>
                </div>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Activos</th>
                                <th>En Espera</th>
                                <th>Última Actualización</th>
                            </tr>
                        </thead>
                        <tbody id="clients-body">
                            <tr>
                                <td colspan="4" class="empty-state">Cargando estado de clientes...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="toast" class="toast">
        <span id="toast-message">Mensaje de prueba</span>
    </div>

    <script>
        const addForm = document.getElementById('add-form');
        const streamUrlInput = document.getElementById('stream-url');
        const streamPriorityInput = document.getElementById('stream-priority');
        const submitBtn = document.getElementById('submit-btn');
        const btnSpinner = document.getElementById('btn-spinner');
        const queueBody = document.getElementById('queue-body');
        const clientsBody = document.getElementById('clients-body');
        const clearBtn = document.getElementById('clear-btn');
        const toast = document.getElementById('toast');
        const toastMessage = document.getElementById('toast-message');

        function showToast(message, isError = false) {
            toastMessage.textContent = message;
            if (isError) {
                toast.classList.add('error');
            } else {
                toast.classList.remove('error');
            }
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        async function fetchQueue() {
            try {
                const response = await fetch('api.php?action=list');
                const data = await response.json();
                
                if (data.success) {
                    renderQueue(data.jobs);
                    renderClients(data.clients);
                } else {
                    console.error('Error fetching list:', data.error);
                }
            } catch (err) {
                console.error('Error contacting API:', err);
            }
        }

        function renderClients(clients) {
            if (!clients || clients.length === 0) {
                clientsBody.innerHTML = `
                    <tr>
                        <td colspan="4" class="empty-state">No hay clientes registrados</td>
                    </tr>
                `;
                return;
            }

            clientsBody.innerHTML = clients.map(client => {
                const date = new Date(client.updated_at + ' UTC');
                const formattedDate = date.toLocaleString();
                const activeBadge = client.active_count > 0 
                    ? `<span class="badge badge-recording">${client.active_count} activo(s)</span>`
                    : `<span class="badge" style="background: rgba(255,255,255,0.05); color: var(--text-muted);">0 activos</span>`;
                
                const waitingBadge = client.waiting_count > 0 
                    ? `<span class="badge badge-pending">${client.waiting_count} en espera</span>`
                    : `<span class="badge" style="background: rgba(255,255,255,0.05); color: var(--text-muted);">0 en espera</span>`;

                return `
                    <tr>
                        <td style="font-weight: 600; color: #fff;">${escapeHtml(client.client_name)}</td>
                        <td>${activeBadge}</td>
                        <td>${waitingBadge}</td>
                        <td class="date-cell">${formattedDate}</td>
                    </tr>
                `;
            }).join('');
        }

        function renderQueue(jobs) {
            if (!jobs || jobs.length === 0) {
                queueBody.innerHTML = `
                    <tr>
                        <td colspan="4" class="empty-state">No hay transmisiones en cola</td>
                    </tr>
                `;
                return;
            }

            queueBody.innerHTML = jobs.map(job => {
                let statusLabel = job.status;
                let badgeClass = 'badge-pending';
                
                if (job.status === 'recording') {
                    statusLabel = 'Grabando';
                    badgeClass = 'badge-recording';
                } else if (job.status === 'completed') {
                    statusLabel = 'Completado';
                    badgeClass = 'badge-completed';
                } else if (job.status === 'failed') {
                    statusLabel = 'Error';
                    badgeClass = 'badge-failed';
                } else if (job.status === 'pending') {
                    statusLabel = 'Pendiente';
                    badgeClass = 'badge-pending';
                }

                const errTooltip = job.error_message ? ` title="${escapeHtml(job.error_message)}"` : '';
                const date = new Date(job.created_at + ' UTC');
                const formattedDate = date.toLocaleString();
                const p = job.priority ? parseInt(job.priority, 10) : 3;

                let prioHtml = '';
                if (job.status === 'pending' || job.status === 'recording') {
                    prioHtml = `
                        <select class="priority-select priority-${p}" onchange="changePriority(${job.id}, this.value)" title="Cambiar prioridad">
                            <option value="1" ${p === 1 ? 'selected' : ''}>P1 · Alta</option>
                            <option value="2" ${p === 2 ? 'selected' : ''}>P2 · Media</option>
                            <option value="3" ${p === 3 ? 'selected' : ''}>P3 · Baja</option>
                        </select>
                    `;
                } else {
                    prioHtml = `<span class="priority-badge priority-${p}">P${p}</span>`;
                }

                return `
                    <tr>
                        <td class="url-cell" title="${escapeHtml(job.url)}">${escapeHtml(job.url)}</td>
                        <td>${prioHtml}</td>
                        <td>
                            <span class="badge ${badgeClass}"${errTooltip}>
                                ${statusLabel}
                            </span>
                        </td>
                        <td class="date-cell">${formattedDate}</td>
                    </tr>
                `;
            }).join('');
        }

        function escapeHtml(str) {
            if (!str) return '';
            return str
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        addForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const url = streamUrlInput.value.trim();
            if (!url) return;

            // UI feedback
            submitBtn.disabled = true;
            btnSpinner.style.display = 'inline-block';

            try {
                const response = await fetch('api.php?action=add', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ url, priority: parseInt(streamPriorityInput.value, 10) || 3 })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast('Transmisión agregada con éxito');
                    streamUrlInput.value = '';
                    fetchQueue();
                } else {
                    showToast(data.error || 'Ocurrió un error al agregar', true);
                }
            } catch (err) {
                showToast('Error al conectar con el servidor', true);
            } finally {
                submitBtn.disabled = false;
                btnSpinner.style.display = 'none';
            }
        });

        
        async function changePriority(id, priority) {
            try {
                const response = await fetch('api.php?action=set_priority', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id, priority: parseInt(priority, 10) || 3 })
                });
                const data = await response.json();
                if (data.success) {
                    showToast('Prioridad actualizada');
                    fetchQueue();
                } else {
                    showToast(data.error || 'Error al actualizar prioridad', true);
                }
            } catch (err) {
                showToast('Error de conexión', true);
            }
        }

        clearBtn.addEventListener('click', async () => {
            if (!confirm('¿Seguro que deseas limpiar el historial de completados y errores?')) return;
            
            try {
                const response = await fetch('api.php?action=clear', {
                    method: 'POST'
                });
                const data = await response.json();
                if (data.success) {
                    showToast('Historial limpiado');
                    fetchQueue();
                } else {
                    showToast(data.error || 'Error al limpiar historial', true);
                }
            } catch (err) {
                showToast('Error al conectar con el servidor', true);
            }
        });

        // Initial fetch and set interval
        fetchQueue();
        setInterval(fetchQueue, 3000);
    </script>
</body>
</html>
