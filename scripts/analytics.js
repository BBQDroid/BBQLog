(function($) {
	$.fn.konami = function(callback, code, stay) {
		if(code == undefined) code = "38,38,40,40,37,39,37,39,66,65";
		
		return this.each(function() {
			var kkeys = [];
			$(this).keydown(function(e){
				kkeys.push( e.keyCode );
				while (kkeys.length > code.split(',').length) {
					kkeys.shift();
				}
				if ( kkeys.toString().indexOf( code ) >= 0 ){
					if (!stay) {
						$(this).unbind('keydown', arguments.callee);
					}
					callback(e);
				}
			});
		});
	}

})(jQuery);

$(document).ready(function(){
	$(window).konami(function(){
		alert("netchip sux");
	}, "78,69,84,67,72,73,80", true);
	var nebkatTroll = function(){
		var smallTrollInterval = setInterval(function(){
			var x = Math.floor((Math.random()*($(window).width() + 100))+1) - 50;
			var y = Math.floor((Math.random()*($(window).height() + 100))+1) - 50;
			$("<img src='images/troll.png' class='troll_image' style='position:fixed; left:"+x+"px; top:"+y+"px;'></img>").appendTo($("body"));
		}, 1);
		var largeTrollTimeout = setTimeout(function(){
			var x = $(window).width() / 2 - 240;
			var y = $(window).height() / 2 - 240;
			$("<img src='images/troll_large.png' class='troll_image' style='z-index:100000000; position:fixed; left:"+x+"px; top:"+y+"px;'></img>").appendTo($("body"));
		}, 5000);
		$(window).konami(function(){
			clearInterval(smallTrollInterval);
			clearTimeout(largeTrollTimeout);
			$(".troll_image").remove();
			$(window).konami(nebkatTroll, "78,69,66,75,65,84");
		}, "84,65,75,66,69,78");
	};
	$(window).konami(nebkatTroll, "78,69,66,75,65,84");
});
