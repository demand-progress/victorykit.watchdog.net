function onStylesLoaded() {
    delete window.stylesLoaded;

    var sheet = document.querySelector('link.styles');
    sheet.media = 'all';
    start();
}

if (window.stylesAreLoaded) {
    onStylesLoaded();
}

function start() {
    
}