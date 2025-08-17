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


    // Close modal when clicking outside
    window.addEventListener('click', (event) => {
        if (event.target === modal) {
            modal.classList.add('hidden');
        }
    });
    form.addEventListener('submit', (e) => {
        e.preventDefault();

        const phone = (modal.dataset.whatsapp || '').replace(/\D+/g, ''); // só dígitos
        if (!phone) {
            alert('Número do WhatsApp não configurado. Defina data-whatsapp no #appointmentModal.');
            return;
        }

        const pet = (inputPet.value || '').trim();
        const species = selSpecies.value;
        const dateISO = inputDate.value; // yyyy-mm-dd
        const time = selTime.value;

        if (!pet || !dateISO || !time) {
            alert('Preencha todos os campos obrigatórios.');
            return;
        }

        // Formata data para dd/mm/aaaa
        const [yyyy, mm, dd] = dateISO.split('-');
        const dateBR = `${dd}/${mm}/${yyyy}`;

        const text =
            `*Agendamento de Consulta*
🐾 *Pet:* ${pet}
🦴 *Espécie:* ${species}
📅 *Data:* ${dateBR}
🕒 *Horário:* ${time}

Olá! Gostaria de confirmar esse horário.`;

        const url = `https://wa.me/${phone}?text=${encodeURIComponent(text)}`;
        window.open(url, '_blank');

        // Opcional: fechar o modal
        closeModal();
        form.reset();
    });
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