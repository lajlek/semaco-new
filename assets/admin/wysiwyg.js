import { Jodit } from 'jodit/es2021/jodit.fat.min.js';
import 'jodit/es2021/jodit.fat.min.css';

const initWysiwyg = () => {
    document.querySelectorAll('textarea[data-wysiwyg]:not([data-wysiwyg-ready])').forEach((textarea) => {
        textarea.setAttribute('data-wysiwyg-ready', '1');
        Jodit.make(textarea, {
            language: 'cs_cz',
            height: 560,
            toolbarAdaptive: false,
            askBeforePasteHTML: false,
            askBeforePasteFromWord: false,
            defaultActionOnPaste: 'insert_clear_html',
            uploader: { insertImageAsBase64URI: false },
            buttons: 'source,|,bold,italic,underline,strikethrough,|,paragraph,ul,ol,|,link,table,image,|,align,|,undo,redo,|,fullsize',
        });
    });
};

document.addEventListener('DOMContentLoaded', initWysiwyg);
