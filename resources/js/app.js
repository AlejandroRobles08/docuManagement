import UIkit from 'uikit';
import Icons from 'uikit/dist/js/uikit-icons';

UIkit.use(Icons);

// Se expone globalmente por si algún script inline (p. ej. en una vista) necesita
// disparar componentes de UIkit manualmente (UIkit.modal(...), UIkit.notification(...), etc.).
window.UIkit = UIkit;

import './document-upload';
