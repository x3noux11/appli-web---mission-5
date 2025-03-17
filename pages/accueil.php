<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-body">
                <h1 class="card-title">Bienvenue à la crèche RAM</h1>
                <p class="card-text">
                    Notre service de crèche propose deux types d'accueil pour vos enfants :
                </p>
                <ul>
                    <li><strong>Accueil régulier</strong> pour les enfants inscrits annuellement</li>
                    <li><strong>Accueil occasionnel</strong> dans les cas où il reste des places disponibles</li>
                </ul>
                <p>
                    Notre équipe de professionnels de la petite enfance est à votre disposition pour accompagner 
                    vos enfants dans leur développement et leur épanouissement.
                </p>
                <?php if (!isLoggedIn()): ?>
                    <div class="mt-4">
                        <a href="index.php?page=register" class="btn btn-primary me-2">S'inscrire</a>
                        <a href="index.php?page=login" class="btn btn-outline-primary">Se connecter</a>
                    </div>
                <?php else: ?>
                    <div class="mt-4">
                        <a href="index.php?page=dashboard" class="btn btn-primary">Accéder à mon espace</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-body">
                <h2 class="card-title">Comment ça marche ?</h2>
                <div class="row">
                    <div class="col-md-4 text-center mb-3">
                        <div class="bg-light rounded-circle d-inline-flex justify-content-center align-items-center mb-3" style="width: 80px; height: 80px;">
                            <i class="fas fa-user-plus fa-2x text-primary"></i>
                        </div>
                        <h5>1. Créez votre compte</h5>
                        <p>Inscrivez-vous pour accéder à notre système de réservation</p>
                    </div>
                    <div class="col-md-4 text-center mb-3">
                        <div class="bg-light rounded-circle d-inline-flex justify-content-center align-items-center mb-3" style="width: 80px; height: 80px;">
                            <i class="fas fa-child fa-2x text-primary"></i>
                        </div>
                        <h5>2. Ajoutez vos enfants</h5>
                        <p>Complétez le profil de vos enfants avec les informations nécessaires</p>
                    </div>
                    <div class="col-md-4 text-center mb-3">
                        <div class="bg-light rounded-circle d-inline-flex justify-content-center align-items-center mb-3" style="width: 80px; height: 80px;">
                            <i class="fas fa-calendar-check fa-2x text-primary"></i>
                        </div>
                        <h5>3. Réservez des places</h5>
                        <p>Inscrivez vos enfants selon vos besoins (régulier ou occasionnel)</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">Informations pratiques</h5>
            </div>
            <div class="card-body">
                <h6><i class="fas fa-clock me-2"></i>Horaires d'ouverture</h6>
                <p>Du lundi au vendredi, de 8h00 à 18h30</p>
                
                <h6><i class="fas fa-map-marker-alt me-2"></i>Adresse</h6>
                <p>123 Rue de la Crèche<br>75000 Paris</p>
                
                <h6><i class="fas fa-phone me-2"></i>Contact</h6>
                <p>Téléphone : +33 1 23 45 67 89<br>
                Email : contact@ram-creche.fr</p>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="card-title mb-0">Application mobile</h5>
            </div>
            <div class="card-body text-center">
                <p>Téléchargez notre application mobile pour réserver facilement des places en crèche occasionnelle.</p>
                <div class="d-flex justify-content-center">
                    <a href="#" class="btn btn-outline-dark me-2">
                        <i class="fab fa-google-play me-2"></i>Google Play
                    </a>
                </div>
                <img src="assets/img/app-mobile.png" alt="Application mobile RAM Crèche" class="img-fluid mt-3" style="max-height: 200px;">
            </div>
        </div>
    </div>
</div>
