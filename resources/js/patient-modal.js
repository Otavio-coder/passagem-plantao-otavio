/**
 * Patient Modal Manager - Versão Simplificada e Otimizada
 */

class PatientModalManager {
    constructor() {
        this.state = {
            modalActive: false,
            scrollPosition: 0
        };
        
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.setupViewport();
    }

    setupEventListeners() {
        // Escutar eventos Livewire
        if (window.Livewire) {
            document.addEventListener('livewire:initialized', () => {
                Livewire.on('modal-opened', () => {
                    this.openModal();
                });
                
                Livewire.on('modal-closed', () => {
                    this.closeModal();
                });
            });
        }
        
        // ESC para fechar
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.state.modalActive) {
                this.closeModal();
            }
        });
        
        // Resize
        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                this.updateViewport();
            }, 150);
        });
    }

    setupViewport() {
        this.updateViewport();
    }

    updateViewport() {
        const vh = window.innerHeight * 0.01;
        document.documentElement.style.setProperty('--vh', `${vh}px`);
    }

    openModal() {
        this.state.scrollPosition = window.pageYOffset;
        this.state.modalActive = true;
        document.body.style.overflow = 'hidden';
        document.body.classList.add('modal-active');
    }

    closeModal() {
        this.state.modalActive = false;
        document.body.style.overflow = '';
        document.body.classList.remove('modal-active');
        window.scrollTo(0, this.state.scrollPosition);
    }
}

// Inicializar
let patientModalManager;

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        patientModalManager = new PatientModalManager();
    });
} else {
    patientModalManager = new PatientModalManager();
}

// Exports globais
window.patientModalManager = patientModalManager;