<?php
// sections/footer_copyright.php - Pied de page copyright
?>
    <!-- FOOTER COPYRIGHT -->
    <footer class="footer-copyright">
        <div class="footer-container">
            <div class="footer-content">
                <div class="footer-brand">
                    <h3 class="footer-logo">SUZOSKY</h3>
                    <p class="footer-tagline">CONCIERGERIE PRIVÉE</p>
                </div>
                <div class="footer-info">
                    <p class="copyright-text">© 2025 Suzosky Conciergerie Privée - Tous droits réservés</p>
                    <p class="legal-text">Plateforme de coursier express à Abidjan, Côte d'Ivoire</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- STYLES FOOTER COPYRIGHT -->
    <style>
    /* ========================================
       FOOTER COPYRIGHT - CHARTE SUZOSKY
       ======================================== */
    .footer-copyright {
        background: var(--gradient-dark);
        border-top: 1px solid var(--glass-border);
        padding: 50px 0 30px;
        margin-top: 100px;
    }

    .footer-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 0 24px;
    }

    .footer-content {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 30px;
    }

    .footer-brand {
        text-align: left;
    }

    .footer-logo {
        font-size: 1.8rem;
        font-weight: 900;
        background: var(--gradient-gold);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        letter-spacing: 2px;
        margin: 0 0 8px 0;
    }

    .footer-tagline {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--primary-gold);
        letter-spacing: 1px;
        margin: 0;
    }

    .footer-info {
        text-align: right;
    }

    .copyright-text {
        font-size: 1rem;
        font-weight: 600;
        color: #fff;
        margin: 0 0 8px 0;
    }

    .legal-text {
        font-size: 0.9rem;
        color: rgba(255, 255, 255, 0.7);
        margin: 0;
    }

    /* Responsive Footer */
    @media (max-width: 767px) {
        .footer-content {
            flex-direction: column;
            text-align: center;
            gap: 20px;
        }
        
        .footer-info {
            text-align: center;
        }
        
        .footer-copyright {
            padding: 30px 0 20px;
        }
    }
    </style>
