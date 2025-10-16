// import './bootstrap';

import ReactDom from 'react-dom';

if (document.getElementById('app')) {
    ReactDom.render(
        <React.StrictMode>
            <App />
        </React.StrictMode>,
        document.getElementById('app')
    );
}
