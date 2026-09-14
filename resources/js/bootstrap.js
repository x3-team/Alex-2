import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;
window.axios.defaults.withXSRFToken = true;

// Вместо статичной фиксации X-CSRF-TOKEN используем перехватчик (interceptor)
// Он будет вытаскивать свежий токен из meta-тега прямо В МОМЕНТ ОТПРАВКИ каждого запроса
window.axios.interceptors.request.use((config) => {
    const token = document.head.querySelector('meta[name="csrf-token"]');
    if (token) {
        config.headers['X-CSRF-TOKEN'] = token.content;
    }
    return config;
});