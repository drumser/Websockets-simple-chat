$(function(){
    ws = new WebSocket("ws://127.0.0.1:8080/");
    ws.onopen = function() { $("#output").append("<p>system: connection is open</p>"); };
    ws.onclose = function() { $("#output").html("<p>system: the connection is closed, I try to reconnect</p>"); };
    ws.onmessage = function(evt) { $('#output').append(evt.data + "<br/>"); console.log(evt.data); };
    $('#output').height($(window).height() - 80);
    $('#output').focus();

    $('#sendmsg').on("click", function() {
        var data = $('#input').val();
        var name = $('#name').val();
        ws.send(name+"|"+data);
        $('#input').val("");
    });
});