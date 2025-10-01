<!DOCTYPE html>
<html>
<head>
    <title>Test État Formulaire</title>
</head>
<body>
    <h1>Test de l'état du formulaire de commande</h1>
    
    <div id="debug-info">
        <h2>Variables initiales</h2>
        <div id="initial-vars"></div>
        
        <h2>État FCM</h2>
        <div id="fcm-state"></div>
        
        <h2>Polling API</h2>
        <div id="api-state"></div>
        
        <h2>Log des événements</h2>
        <div id="event-log" style="height: 300px; overflow-y: scroll; border: 1px solid #ccc; padding: 10px; font-family: monospace; font-size: 12px;"></div>
    </div>

    <script>
        // Override console.log pour capturer les logs
        const eventLog = document.getElementById('event-log');
        const originalLog = console.log;
        console.log = function(...args) {
            originalLog.apply(console, args);
            const timestamp = new Date().toLocaleTimeString();
            eventLog.innerHTML += `[${timestamp}] ${args.map(arg => 
                typeof arg === 'object' ? JSON.stringify(arg) : String(arg)
            ).join(' ')}<br>`;
            eventLog.scrollTop = eventLog.scrollHeight;
        };

        // Variables que le vrai index injecte
        window.COMMERCIAL_FALLBACK_MESSAGE = "Nos coursiers sont actuellement très sollicités. Restez sur cette page — des coursiers se libèrent dans un instant et le formulaire se rouvrira automatiquement pour vous permettre de commander immédiatement. Merci pour votre patience !";
        window.initialCoursierAvailability = true;
        window.hasClientSession = false;
        window.initialCoursierMessage = "Nos coursiers sont actuellement très sollicités. Restez sur cette page — des coursiers se libèrent dans un instant et le formulaire se rouvrira automatiquement pour vous permettre de commander immédiatement. Merci pour votre patience !";
        window.COURSIER_LOCK_DELAY_MS = 60000;
        window.COURSIER_POLL_INTERVAL_MS = 1000;
        window.currentClient = false;

        // Afficher les variables initiales
        document.getElementById('initial-vars').innerHTML = `
            <strong>initialCoursierAvailability:</strong> ${window.initialCoursierAvailability}<br>
            <strong>hasClientSession:</strong> ${window.hasClientSession}<br>
            <strong>currentClient:</strong> ${window.currentClient}<br>
            <strong>COURSIER_POLL_INTERVAL_MS:</strong> ${window.COURSIER_POLL_INTERVAL_MS}
        `;

        console.log('🚀 Variables initiales configurées');
        console.log('initialCoursierAvailability:', window.initialCoursierAvailability);
        console.log('hasClientSession:', window.hasClientSession);
        console.log('currentClient:', window.currentClient);
    </script>

    <!-- Inclure les scripts du vrai système -->
    <?php include 'sections_index/js_form_handling.php'; ?>
    <?php include 'sections_index/js_initialization.php'; ?>

    <script>
        // Monitor l'état FCM
        function updateFCMState() {
            document.getElementById('fcm-state').innerHTML = `
                <strong>fcmCoursierAvailable:</strong> ${window.fcmCoursierAvailable}<br>
                <strong>fcmCoursierMessage:</strong> ${window.fcmCoursierMessage || 'N/A'}<br>
                <strong>setFCMCoursierStatus exists:</strong> ${typeof window.setFCMCoursierStatus === 'function'}
            `;
        }

        // Monitor l'état API
        async function updateAPIState() {
            try {
                const response = await fetch('/COURSIER_LOCAL/api/get_coursier_availability.php');
                const data = await response.json();
                document.getElementById('api-state').innerHTML = `
                    <strong>API available:</strong> ${data.available}<br>
                    <strong>API active_count:</strong> ${data.active_count}<br>
                    <strong>API fresh_count:</strong> ${data.fresh_count}<br>
                    <strong>API message:</strong> ${data.message}
                `;
            } catch (error) {
                document.getElementById('api-state').innerHTML = `<strong>Erreur API:</strong> ${error.message}`;
            }
        }

        // Mettre à jour toutes les 2 secondes
        setInterval(() => {
            updateFCMState();
            updateAPIState();
        }, 2000);

        // Premier update immédiat
        setTimeout(() => {
            updateFCMState();
            updateAPIState();
        }, 1000);
    </script>
</body>
</html>