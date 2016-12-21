/*
 * jQuery LiColl v 2.0
 * http://mmmagnit.ks.ua
 *
 * Copyright 2012, Linnik Yura
 * Free to use
 * 
 * Jule 2012
 */
/* 
$('.list_coll').liColl({
	c_unit: '%', // '%' или 'px' При указании '%' — ширина 'c_width' игнорируется
	n_coll: 4, //колличество колонок
	c_width: 200, //Ширина колонок в 'px'
	p_left: 5 //отступ слева %						
});
updateColl($('.list_coll')) //обновить список
*/
var updateColl
jQuery.fn.liColl = function(options){
	// настройки по умолчанию
	var o = jQuery.extend({
		n_coll: 2, //колличество колонок
		c_unit: '%', //Единица измерения
		c_width: 300, //Ширина колонок
		p_left: 1 //отступ слева %
	},options);
	updateColl = function update(listWrap){
		listWrap.children('.coll_s').each(function(){
			$(this).children().unwrap();	
		})
		listWrap.liColl(options);
	}
	return this.each(function(){
		element = jQuery(this).css({overflow:'hidden'});
		nc = o.n_coll;
		pl = o.p_left;
		i = 1;
		c_un = 'px';
		if(options.c_unit != c_un){
			coll_w = Math.floor(100/nc);
			coll_w = coll_w - pl;
		}else{
			coll_w = options.c_width - pl;
		};
		num_1 = element.children('li').length;
		create();
		function create(){
			n_end = Math.ceil(num_1/nc);
			var cc = jQuery('<div />').addClass("coll_s c_" + i).css({width:coll_w+options.c_unit,paddingLeft:pl+options.c_unit,float:'left',clear:'right'})
			//element_2.append();
			element.children('li').slice(0,n_end).wrapAll(cc);
			if(num_1 != n_end){
				i++;
				nc--;
				num_1 = num_1 - n_end;
				create();
			};
		};
	});
};