(function (wp) {
'use strict';
if (!wp || !wp.blocks || !wp.blockEditor || !wp.element) return;
var name="intercargo/process";
if (wp.blocks.getBlockType && wp.blocks.getBlockType(name)) return;
var ce=wp.element.createElement, Fragment=wp.element.Fragment;
var useBlockProps=wp.blockEditor.useBlockProps, RichText=wp.blockEditor.RichText, InspectorControls=wp.blockEditor.InspectorControls;
var PanelBody=wp.components.PanelBody, TextControl=wp.components.TextControl, Button=wp.components.Button;
var MediaUpload=wp.blockEditor.MediaUpload, MediaUploadCheck=wp.blockEditor.MediaUploadCheck;
var useSelect=wp.data.useSelect;
var fallbackRows=[{"title": "Talk to a person", "copy": "Tell us what you are importing and how often. A senior team member replies the same business day."}, {"title": "Get your plan and price", "copy": "One written quote covering every leg and every fee. No surprises later, that is the point."}, {"title": "Your freight moves", "copy": "We book, clear and deliver, and you hear from us at every milestone without asking."}];
function own(o,k){return Object.prototype.hasOwnProperty.call(o||{},k);}
function data(a){return a&&a.data&&typeof a.data==='object'?a.data:{};}
function text(a,n,l,f){if(own(a,n))return a[n]==null?'':String(a[n]);var d=data(a);return own(d,l)&&d[l]!=null?String(d[l]):f;}
function rows(a,n,l){if(own(a,n)&&Array.isArray(a[n])&&a[n].length)return a[n].map(norm);var d=data(a), out=[];
 if(Array.isArray(d[l])&&d[l].length) return d[l].map(norm);
 var count=Number(d[l])||0; if(!count){Object.keys(d).forEach(function(k){var m=k.match(new RegExp('^'+l+'_(\\d+)_'));if(m)count=Math.max(count,Number(m[1])+1);});}
 for(var i=0;i<count;i++){var r={title:d[l+'_'+i+'_title']||'',copy:d[l+'_'+i+'_copy']||''};if(r.title||r.copy)out.push(r);}
 return out.length?out:fallbackRows.map(norm);
}
function norm(r){return {title:r&&r.title?String(r.title):'',copy:r&&r.copy?String(r.copy):''};}
function idval(a,n,l){if(own(a,n)){var x=Number(a[n]);return Number.isInteger(x)&&x>0?x:0;}var x=Number(data(a)[l]);return Number.isInteger(x)&&x>0?x:0;}
function mediaUrl(m){return m&&m.source_url?String(m.source_url):(m&&m.guid&&m.guid.rendered?String(m.guid.rendered):'');}
wp.blocks.registerBlockType(name,{
 edit:function(props){
  var a=props.attributes||{}, set=props.setAttributes;
  var heading=text(a,"heading","heading","How it starts.");
  var list=rows(a,"steps","steps");
  var anchor=text(a,'sectionAnchor','section_anchor','');
  var bgId=idval(a,'backgroundImageId','background_image');
  var media=useSelect?useSelect(function(select){var core=select('core');return bgId&&core?core.getMedia(bgId):null;},[bgId]):null;
  var bg=own(a,'backgroundImageUrl')&&a.backgroundImageUrl?String(a.backgroundImageUrl):mediaUrl(media);
  function setRow(i,key,value){var next=list.map(norm);next[i][key]=value;set({steps:next});}
  function setBg(m){var id=Number(m&&m.id); if(!Number.isInteger(id)||id<=0||!m.url)return;set({backgroundImageId:id,backgroundImageUrl:String(m.url)});}
  var bp=useBlockProps({className:"how-section section-pad",style:bg?{backgroundImage:'url("'+bg.replace(/"/g,'%22')+'")'}:undefined});
  var inspector=ce(InspectorControls,null,ce(PanelBody,{title:'Section settings',initialOpen:true},
    ce(TextControl,{label:'Section anchor',value:anchor,onChange:function(v){set({sectionAnchor:v});}}),
    MediaUpload&&MediaUploadCheck?ce(MediaUploadCheck,null,ce(MediaUpload,{allowedTypes:['image'],value:bgId||undefined,onSelect:setBg,render:function(c){return ce(Fragment,null,ce(Button,{variant:'secondary',onClick:c.open},bg?'Replace section background':'Choose section background'),bg?ce(Button,{variant:'tertiary',isDestructive:true,onClick:function(){set({backgroundImageId:0,backgroundImageUrl:''});}},'Remove background'):null);}})):null
  ));
  return ce(Fragment,null,inspector,ce('section',bp,
    ce('div',{className:'container section-head-gap'},
      ce(RichText,{tagName:'h2',className:'section-title',value:heading,allowedFormats:[],onChange:function(v){set({heading:v});}}),
      ce('div',{className:'three-cols'},list.map(function(r,i){return ce('article',{className:'reason-card',key:i},
        ce(RichText,{tagName:'h3',value:r.title,allowedFormats:[],onChange:function(v){setRow(i,'title',v);}}),
        ce(RichText,{tagName:'p',value:r.copy,allowedFormats:[],onChange:function(v){setRow(i,'copy',v);}})
      );}))
    )
  ));
 },
 save:function(){return null;}
});
})(window.wp);
