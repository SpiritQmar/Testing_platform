document.addEventListener('DOMContentLoaded', () => {
    const themeToggle = document.getElementById('themeToggle');

    if (localStorage.getItem('theme') === 'dark') {
        document.body.classList.add('dark');
    }

    if (themeToggle) {
        themeToggle.textContent = document.body.classList.contains('dark') ? '☀' : '☾';
        themeToggle.onclick = () => {
            document.body.classList.toggle('dark');
            localStorage.setItem('theme', document.body.classList.contains('dark') ? 'dark' : 'light');
            themeToggle.textContent = document.body.classList.contains('dark') ? '☀' : '☾';
        };
    }
});
