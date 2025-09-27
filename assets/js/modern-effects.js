// Module principal pour le site moderne
export class AdvancedAnimations {
    constructor() {
        this.initGSAP();
        this.setupAdvancedAnimations();
    }

    async initGSAP() {
        // Charger GSAP dynamiquement pour les animations avancées
        try {
            const gsap = await import('https://unpkg.com/gsap@3.12.2/index.js');
            this.gsap = gsap.default;
            this.setupGSAPAnimations();
        } catch (error) {
            console.log('GSAP non disponible, utilisation des animations CSS');
        }
    }

    setupGSAPAnimations() {
        if (!this.gsap) return;

        // Animation de texte lettre par lettre
        this.gsap.registerPlugin(TextPlugin);
        
        // Timeline pour le hero
        const heroTl = this.gsap.timeline();
        heroTl.from('.hero-title', {
            duration: 1.5,
            y: 100,
            opacity: 0,
            ease: 'power3.out'
        })
        .from('.hero-subtitle', {
            duration: 1,
            y: 50,
            opacity: 0,
            ease: 'power2.out'
        }, '-=0.5')
        .from('.cta-button', {
            duration: 0.8,
            scale: 0.8,
            opacity: 0,
            ease: 'back.out(1.7)'
        }, '-=0.3');

        // Animations au scroll
        this.gsap.utils.toArray('.service-card').forEach(card => {
            this.gsap.fromTo(card, {
                y: 100,
                opacity: 0,
                scale: 0.9
            }, {
                y: 0,
                opacity: 1,
                scale: 1,
                duration: 1,
                ease: 'power2.out',
                scrollTrigger: {
                    trigger: card,
                    start: 'top 80%',
                    end: 'bottom 20%',
                    toggleActions: 'play none none reverse'
                }
            });
        });
    }

    setupAdvancedAnimations() {
        // Effet de parallax avancé
        this.setupParallaxEffect();
        
        // Animations de hover sophistiquées
        this.setupHoverEffects();
        
        // Curseur personnalisé
        this.setupCustomCursor();
    }

    setupParallaxEffect() {
        const parallaxElements = document.querySelectorAll('[data-parallax]');
        
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            
            parallaxElements.forEach(element => {
                const speed = element.dataset.parallax || 0.5;
                const yPos = -(scrolled * speed);
                element.style.transform = `translateY(${yPos}px)`;
            });
        });
    }

    setupHoverEffects() {
        const cards = document.querySelectorAll('.service-card');
        
        cards.forEach(card => {
            card.addEventListener('mouseenter', () => {
                this.createRippleEffect(card);
            });
        });
    }

    createRippleEffect(element) {
        const ripple = document.createElement('div');
        ripple.style.cssText = `
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            transform: scale(0);
            animation: ripple 0.6s linear;
            pointer-events: none;
        `;
        
        const rect = element.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        ripple.style.width = ripple.style.height = size + 'px';
        ripple.style.left = (rect.width / 2 - size / 2) + 'px';
        ripple.style.top = (rect.height / 2 - size / 2) + 'px';
        
        element.appendChild(ripple);
        
        setTimeout(() => {
            ripple.remove();
        }, 600);
    }

    setupCustomCursor() {
        const cursor = document.createElement('div');
        cursor.className = 'custom-cursor';
        cursor.style.cssText = `
            position: fixed;
            width: 20px;
            height: 20px;
            border: 2px solid #00f2fe;
            border-radius: 50%;
            pointer-events: none;
            z-index: 9999;
            transition: transform 0.1s ease;
            mix-blend-mode: difference;
        `;
        document.body.appendChild(cursor);

        document.addEventListener('mousemove', (e) => {
            cursor.style.left = e.clientX - 10 + 'px';
            cursor.style.top = e.clientY - 10 + 'px';
        });

        // Effet sur les éléments interactifs
        const interactiveElements = document.querySelectorAll('a, button, .service-card');
        interactiveElements.forEach(el => {
            el.addEventListener('mouseenter', () => {
                cursor.style.transform = 'scale(2)';
                cursor.style.backgroundColor = 'rgba(0, 242, 254, 0.2)';
            });
            
            el.addEventListener('mouseleave', () => {
                cursor.style.transform = 'scale(1)';
                cursor.style.backgroundColor = 'transparent';
            });
        });
    }
}

// Module pour la gestion de l'état
export class StateManager {
    constructor() {
        this.state = {
            currentSection: 'home',
            flyerVisible: false,
            menuOpen: false
        };
        this.subscribers = [];
    }

    subscribe(callback) {
        this.subscribers.push(callback);
    }

    setState(newState) {
        this.state = { ...this.state, ...newState };
        this.subscribers.forEach(callback => callback(this.state));
    }

    getState() {
        return this.state;
    }
}

// Module pour les performances
export class PerformanceManager {
    constructor() {
        this.setupIntersectionObserver();
        this.setupLazyLoading();
        this.monitorPerformance();
    }

    setupIntersectionObserver() {
        if (!window.IntersectionObserver) return;

        const options = {
            root: null,
            rootMargin: '50px',
            threshold: 0.1
        };

        this.observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    
                    // Lazy load content
                    if (entry.target.dataset.lazy) {
                        this.loadContent(entry.target);
                    }
                }
            });
        }, options);

        // Observer tous les éléments avec data-observe
        document.querySelectorAll('[data-observe]').forEach(el => {
            this.observer.observe(el);
        });
    }

    setupLazyLoading() {
        // Lazy loading pour les images
        const images = document.querySelectorAll('img[data-src]');
        
        if ('loading' in HTMLImageElement.prototype) {
            // Support natif du lazy loading
            images.forEach(img => {
                img.loading = 'lazy';
                img.src = img.dataset.src;
            });
        } else {
            // Fallback avec Intersection Observer
            this.setupImageLazyLoading(images);
        }
    }

    setupImageLazyLoading(images) {
        const imageObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    imageObserver.unobserve(img);
                }
            });
        });

        images.forEach(img => imageObserver.observe(img));
    }

    loadContent(element) {
        // Simulation de chargement de contenu dynamique
        const content = element.dataset.content;
        if (content) {
            fetch(`/api/content/${content}`)
                .then(response => response.text())
                .then(html => {
                    element.innerHTML = html;
                })
                .catch(error => {
                    console.log('Erreur de chargement:', error);
                });
        }
    }

    monitorPerformance() {
        // Monitoring des performances avec Performance API
        if ('performance' in window) {
            window.addEventListener('load', () => {
                setTimeout(() => {
                    const perfData = performance.getEntriesByType('navigation')[0];
                    const loadTime = perfData.loadEventEnd - perfData.loadEventStart;
                    
                    console.log(`Temps de chargement: ${loadTime}ms`);
                    
                    // Envoyer les métriques (optionnel)
                    this.sendMetrics({
                        loadTime,
                        domContentLoaded: perfData.domContentLoadedEventEnd - perfData.domContentLoadedEventStart,
                        firstPaint: performance.getEntriesByType('paint')[0]?.startTime || 0
                    });
                }, 0);
            });
        }
    }

    sendMetrics(metrics) {
        // Envoi des métriques vers un service d'analytics
        if (navigator.sendBeacon) {
            navigator.sendBeacon('/analytics', JSON.stringify(metrics));
        }
    }
}

// CSS pour les animations personnalisées
const customCSS = `
@keyframes ripple {
    to {
        transform: scale(4);
        opacity: 0;
    }
}

.visible {
    opacity: 1 !important;
    transform: translateY(0) !important;
}

.lazy {
    opacity: 0;
    transition: opacity 0.3s;
}

.loading-skeleton {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: loading 1.5s infinite;
}

@keyframes loading {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -200% 0;
    }
}

/* Effet de morphing sur les boutons */
.morph-button {
    transition: all 0.3s cubic-bezier(0.4, 0.0, 0.2, 1);
}

.morph-button:hover {
    border-radius: 50px;
    transform: scale(1.05);
}

/* Gradient animé */
.animated-gradient {
    background: linear-gradient(-45deg, #667eea, #764ba2, #f093fb, #f5576c);
    background-size: 400% 400%;
    animation: gradientShift 4s ease infinite;
}

@keyframes gradientShift {
    0% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
    100% {
        background-position: 0% 50%;
    }
}
`;

// Injection du CSS personnalisé
const style = document.createElement('style');
style.textContent = customCSS;
document.head.appendChild(style);
