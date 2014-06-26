(
    function (window){
        window.whBoard.lang.getString = function (string){
            return window.whBoard.lang.message[string];
        }
    }
)(window);