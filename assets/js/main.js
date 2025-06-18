// Modal functionality
const modal = document.getElementById('appointmentModal');
const openModalButtons = document.querySelectorAll('[id^="openModal"]');
const closeModalButton = document.getElementById('closeModal');

openModalButtons.forEach(button => {
    button.addEventListener('click', () => {
        modal.classList.remove('hidden');
    });
});

closeModalButton.addEventListener('click', () => {
    modal.classList.add('hidden');
});

// Close modal when clicking outside
window.addEventListener('click', (event) => {
    if (event.target === modal) {
        modal.classList.add('hidden');
    }
});

// Animação de elementos quando entram na tela
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('active');
        }
    });
}, { threshold: 0.1 });

document.querySelectorAll('.animate-on-scroll').forEach(el => {
    observer.observe(el);
});

// Efeito de onda no ícone da pata
const pawIcon = document.querySelector('.fa-paw');
setInterval(() => {
    pawIcon.classList.add('wave');
    setTimeout(() => {
        pawIcon.classList.remove('wave');
    }, 2000);
}, 5000);

// Animação aleatória nas patinhas
const paws = document.querySelectorAll('.paw-print');
paws.forEach(paw => {
    setInterval(() => {
        paw.classList.toggle('pet-shake');
        setTimeout(() => {
            paw.classList.toggle('pet-shake');
        }, 1000);
    }, Math.random() * 5000 + 2000);
});