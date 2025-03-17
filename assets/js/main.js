// Script principal pour l'application RAM Crèche

// Attendre que le document soit prêt
document.addEventListener('DOMContentLoaded', function() {
    // Activer les tooltips Bootstrap
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Activer les popovers Bootstrap
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    // Fermer automatiquement les alertes après 5 secondes
    window.setTimeout(function() {
        var alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
        alerts.forEach(function(alert) {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
    
    // Gestion des formulaires d'inscription occasionnelle
    setupInscriptionOccasionnelleForm();
    
    // Gestion du planning
    setupPlanning();
});

/**
 * Configuration du formulaire d'inscription occasionnelle
 */
function setupInscriptionOccasionnelleForm() {
    const enfantSelect = document.getElementById('enfant_id');
    const groupeSelect = document.getElementById('groupe_age_id');
    
    if (enfantSelect && groupeSelect) {
        // Lors du changement d'enfant, vérifier l'âge et suggérer un groupe approprié
        enfantSelect.addEventListener('change', function() {
            const enfantId = this.value;
            if (enfantId) {
                // On pourrait récupérer l'âge de l'enfant par une requête AJAX
                // et suggérer automatiquement le groupe adapté
                // Pour l'instant, on laisse l'utilisateur choisir
            }
        });
        
        // Lors du changement de groupe, vérifier les horaires disponibles
        const dateInput = document.getElementById('date_garde');
        if (dateInput) {
            function checkDisponibilite() {
                const groupeId = groupeSelect.value;
                const date = dateInput.value;
                
                if (groupeId && date) {
                    // On pourrait faire une requête AJAX pour récupérer les disponibilités
                    // et mettre à jour les heures disponibles
                }
            }
            
            groupeSelect.addEventListener('change', checkDisponibilite);
            dateInput.addEventListener('change', checkDisponibilite);
        }
    }
}

/**
 * Configuration de la page de planning
 */
function setupPlanning() {
    // Mettre en évidence la date actuelle dans le planning
    const today = new Date().toISOString().split('T')[0];
    const planningCells = document.querySelectorAll('.planning-table td[data-date]');
    
    planningCells.forEach(cell => {
        if (cell.dataset.date === today) {
            cell.classList.add('bg-light');
            cell.setAttribute('title', 'Aujourd\'hui');
        }
    });
}

/**
 * Fonction pour confirmer une action
 * @param {string} message - Message de confirmation
 * @returns {boolean} - True si confirmé, false sinon
 */
function confirmAction(message) {
    return confirm(message);
}

/**
 * Fonction pour formater une date
 * @param {Date} date - Date à formater
 * @param {string} format - Format souhaité ('short', 'long', 'time')
 * @returns {string} - Date formatée
 */
function formatDate(date, format = 'short') {
    if (!(date instanceof Date)) {
        date = new Date(date);
    }
    
    const options = {
        short: { day: '2-digit', month: '2-digit', year: 'numeric' },
        long: { day: '2-digit', month: 'long', year: 'numeric' },
        time: { hour: '2-digit', minute: '2-digit' }
    };
    
    return date.toLocaleDateString('fr-FR', options[format]);
}

/**
 * Fonction pour calculer l'âge en mois à partir d'une date de naissance
 * @param {string} dateNaissance - Date de naissance au format YYYY-MM-DD
 * @returns {number} - Âge en mois
 */
function calculerAgeMois(dateNaissance) {
    const today = new Date();
    const birthDate = new Date(dateNaissance);
    let months = (today.getFullYear() - birthDate.getFullYear()) * 12;
    months -= birthDate.getMonth();
    months += today.getMonth();
    
    // Ajuster si le jour du mois n'est pas encore passé
    if (today.getDate() < birthDate.getDate()) {
        months--;
    }
    
    return months;
}
