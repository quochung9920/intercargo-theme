(function (wp, window) {
'use strict';
if (!wp || !wp.blocks || !wp.blockEditor || !wp.element || !wp.components || !wp.serverSideRender) return;
var ce=wp.element.createElement, Fragment=wp.element.Fragment, useEffect=wp.element.useEffect, useState=wp.element.useState;
var InspectorControls=wp.blockEditor.InspectorControls, useBlockProps=wp.blockEditor.useBlockProps;
var PanelBody=wp.components.PanelBody, SelectControl=wp.components.SelectControl, TextControl=wp.components.TextControl;
var ServerSideRender=wp.serverSideRender, apiFetch=wp.apiFetch;
var config=window.intercargoFormConfig||{};
function register(name,fixedVariant,title){
 if(wp.blocks.getBlockType&&wp.blocks.getBlockType(name))return;
 wp.blocks.registerBlockType(name,{edit:function(props){
  var a=props.attributes||{}, set=props.setAttributes, state=useState([]), providers=state[0], setProviders=state[1];
  useEffect(function(){if(!apiFetch)return;apiFetch({path:config.restPath||'/intercargo/v1/forms'}).then(function(r){setProviders(r&&Array.isArray(r.providers)?r.providers:[]);}).catch(function(){setProviders([]);});},[]);
  var provider=a.provider||'', current=providers.find(function(p){return p.id===provider;});
  var providerOptions=[{label:'Select form provider',value:''}].concat(providers.map(function(p){return{label:p.title,value:p.id};}));
  var formOptions=[{label:'Select form',value:''}].concat(current&&Array.isArray(current.forms)?current.forms.map(function(f){return{label:f.title,value:String(f.id)};}):[]);
  var attrs=Object.assign({},a); if(fixedVariant)attrs.variant=fixedVariant;
  var inspector=ce(InspectorControls,null,ce(PanelBody,{title:title||'Form',initialOpen:true},
    ce(SelectControl,{label:'Provider',value:provider,options:providerOptions,onChange:function(v){set({provider:v,formId:''});}}),
    provider?ce(SelectControl,{label:'Form',value:String(a.formId||''),options:formOptions,onChange:function(v){set({formId:String(v)});}}):null,
    name==='intercargo/form'?ce(SelectControl,{label:'Visual variant',value:a.variant||'default',options:[{label:'Default',value:'default'},{label:'Hero inline',value:'hero'},{label:'Guide inline',value:'guide'},{label:'Enquiry',value:'enquiry'}],onChange:function(v){set({variant:v});}}):null,
    name==='intercargo/form'?ce(TextControl,{label:'Accessible label',value:a.label||'',onChange:function(v){set({label:v});}}):null
  ));
  return ce(Fragment,null,inspector,ce('div',useBlockProps({className:'intercargo-form-editor'}),ce(ServerSideRender,{block:name,attributes:attrs,skipBlockSupportAttributes:true})));
 },save:function(){return null;}});
}
register('intercargo/form',null,'Form');
register('intercargo/hero-email-form','hero','Hero form');
register('intercargo/guide-email-form','guide','Guide form');
})(window.wp,window);
