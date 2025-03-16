// Fonction pour vérifier la file d'attente toutes les 3 secondes (plus fréquent)
function checkQueue() {
    fetch('/api/game/queue.php?action=check')
        .then(response => {
            if (!response.ok) {
                throw new Error('Erreur réseau');
            }
            return response.json();
        })
        .then(data => {
            console.log('Réponse de vérification:', data); // Ajout de log pour déboguer
            
            if (data.matched) {
                // Rediriger vers la nouvelle partie
                window.location.href = '/game/board.php?id=' + data.game_id;
            } else {
                // Mettre à jour le temps d'attente affiché
                if (data.wait_time) {
                    document.getElementById('wait-time').textContent = data.wait_time;
                }
                
                // Continuer à vérifier plus fréquemment (toutes les 3 secondes)
                setTimeout(checkQueue, 3000);
            }
        })
        .catch(error => {
            console.error('Erreur lors de la vérification de la file d\'attente:', error);
            // Réessayer après un délai en cas d'erreur
            setTimeout(checkQueue, 3000);
        });
} 