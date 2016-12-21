function AjaxLoader() {
    $('body').append('<div id="loadingDiv"></div>');
    $('#loadingDiv')
        .append('<p id="loadingText"></p>')
        .css('background', 'url(js/ajax.gif) no-repeat 50% 25%') 
        //.css('padding', 'center')
        .css('background-color', '#F5F5F5')
        .css('border', '1px solid #00008B')
        .css('height', '60px')
        .css('width', '60px')
        .center()
        .hide(); // изначально скрываем сообщение
    $('#loadingText')
        .css('text-align', 'center')
        .css('font', '20px bolder')
        .css('font-family', 'Segoe UI, Tahoma, Arial');
}
