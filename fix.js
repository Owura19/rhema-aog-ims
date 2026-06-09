require('fs').writeFileSync('resources/js/bootstrap.js', 'import axios from 'axios';
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
', 'utf8')