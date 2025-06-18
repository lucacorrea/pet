   // Funções para controlar o modal
        const modal = document.getElementById('appointmentModal');
        const openButtons = document.querySelectorAll('#openModal, #openModal2');
        const closeButton = document.getElementById('closeModal');
        
        openButtons.forEach(button => {
            button.addEventListener('click', () => {
                modal.classList.remove('hidden');
            });
        });
        
        closeButton.addEventListener('click', () => {
            modal.classList.add('hidden');
        });
        
        // Fechar modal ao clicar fora
        window.addEventListener('click', (event) => {
            if (event.target === modal) {
                modal.classList.add('hidden');
            }
        });
        
        // Efeito de scroll suave
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });