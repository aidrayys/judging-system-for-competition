// Performance: axios used to be imported here and exposed as window.axios, but
// no script in the application uses it (evaluate.js and login.js use fetch()).
// Dropping it removes ~35 KB (minified) from the bundle loaded on every page.
// If axios is ever needed again, restore:
//   import axios from 'axios';
//   window.axios = axios;
//   window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
