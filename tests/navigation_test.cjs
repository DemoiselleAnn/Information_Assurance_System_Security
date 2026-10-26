const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(__dirname + '/../js/sidebar_scroll.js', 'utf8');
class Element {
    constructor() { this.dataset={}; this.attrs={}; this.classList={contains:()=>false}; this.parentElement=null; this.clientHeight=800; this.scrollTop=0; this.scrollLeft=0; this.listeners={}; }
    getAttribute(k){return this.attrs[k]??null;} setAttribute(k,v){this.attrs[k]=v;} removeAttribute(k){delete this.attrs[k];} hasAttribute(k){return k in this.attrs;}
    closest(){return null;} matches(){return false;} querySelector(){return null;} querySelectorAll(){return [];} contains(){return true;}
    getBoundingClientRect(){return {top:0,height:800,bottom:800};} scrollTo(p){this.scrollTop=p.top;this.scrollLeft=p.left;} focus(){}
    addEventListener(k,fn){(this.listeners[k]??=[]).push(fn);}
}
class Form extends Element { constructor(){super();this.elements=[];this.values=[];} }
class Data extends Map {constructor(form){super(form.values);}append(k,v){this.set(k,v);} }
function setup(url='http://localhost/Cakedeco/php/app.php?page=security_logs',member=false){
    const root=new Element(), content=new Element(), sidebar=new Element(), body=new Element(), section=new Element();
    root.scrollTop=1500;root.clientHeight=800;section.id='login-sessions';section.absolute=1100;section.height=900;
    root.getBoundingClientRect=()=>({top:member?76:0,height:800,bottom:member?876:800});
    section.getBoundingClientRect=()=>({top:(member?76:0)+section.absolute-root.scrollTop,height:section.height,bottom:(member?76:0)+section.absolute-root.scrollTop+section.height});
    content.parentElement=member?root:body;root.parentElement=body;
    content.querySelectorAll=q=>q.includes('section')||q.startsWith('[data-scroll')?[section]:[];
    const document={baseURI:url,body,documentElement:root,scrollingElement:member?new Element():root,readyState:'loading',addEventListener(){},head:{querySelectorAll:()=>[]},
        querySelector(q){if(q==='.dashboard-content')return member?null:content;if(q==='.dashboard-main .content-wrap')return member?content:null;if(q==='.sidebar')return sidebar;if(q==='.top-header')return {getBoundingClientRect:()=>({height:90})};return null;},getElementById(id){return id===section.id?section:null;}};
    const calls=[];const window={location:new URL(url),history:{state:null,replaceState(state){this.state=state;}},__calls:calls};
    const sandbox={window,document,URL,URLSearchParams,Element,HTMLFormElement:Form,FormData:Data,Set,Map,Promise,AbortController,requestAnimationFrame:fn=>fn(),sessionStorage:{},getComputedStyle:el=>({overflowY:member&&el===root?'auto':'visible'}),Event:class{}};
    const exposed=source.replace("    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeScrollCoordinator);", "    navigate = function(url, options){window.__calls.push({url,options});}; window.testNavigation={isDashboardUrl,tabKey,carryFilters,scrollRoot,capture,restorePosition,handleFormSubmit,handleLinkClick};\n    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeScrollCoordinator);");
    vm.runInNewContext(exposed,sandbox);
    return {api:window.testNavigation,root,content,section,document,window,calls};
}
for(const prefix of ['/Cakedeco/php/','/another/install/php/','/nested-install/php/']){
    const {api}=setup('http://localhost'+prefix+'app.php?page=security_logs');
    assert(api.isDashboardUrl(new URL('http://localhost'+prefix+'app.php?page=security_logs&page_no=2')));
    assert(!api.isDashboardUrl(new URL('http://localhost/php/app.php?page=security_logs')));
    assert(!api.isDashboardUrl(new URL('https://elsewhere.test'+prefix+'app.php?page=security_logs')));
    for(const page of ['login','register','first_login_setup'])assert(!api.isDashboardUrl(new URL('http://localhost'+prefix+'app.php?page='+page)));
}
for(const member of [false,true]){
    const {api,root,section,content}=setup(undefined,member);
    assert.equal(api.scrollRoot(content),root,'Correct document/member scroll root');
    const context=api.capture();assert.equal(context.offset,400);
    api.restorePosition(context,new URL('http://localhost/Cakedeco/php/app.php?page=security_logs'),{});assert.equal(root.scrollTop,1500,'Retain table-relative position');
    section.height=200;api.restorePosition(context,new URL('http://localhost/Cakedeco/php/app.php?page=security_logs'),{});assert.equal(root.scrollTop,member?1084:994,'Short page reveals heading');
    api.restorePosition(null,new URL('http://localhost/Cakedeco/php/app.php?page=account_management'),{});assert.equal(root.scrollTop,0,'New tab begins at top');
}
{
    const {api}=setup();const result=api.carryFilters(new URL('http://localhost/Cakedeco/php/app.php?page=security_logs&page_no=3'),new URL('http://localhost/Cakedeco/php/app.php?page=security_logs&q=test&role=USER&page_no=2'));
    assert.equal(result.searchParams.get('q'),'test');assert.equal(result.searchParams.get('role'),'USER');assert.equal(result.searchParams.get('page_no'),'3');
}
{
    const {api,calls}=setup();
    function form(method='post',action='app.php?page=security_logs') {const f=new Form();f.attrs={method,action};f.values=[['page','security_logs'],['csrf_token','offline-csrf'],['q','test']];return f;}
    const f=form(), button=new Element();button.type='submit';button.name='action';button.value='save';button.disabled=false;f.elements=[button];
    const event={target:f,submitter:button,defaultPrevented:false,preventDefault(){this.defaultPrevented=true;}};api.handleFormSubmit(event);
    assert(event.defaultPrevented);assert.equal(calls.length,1);assert.equal(calls[0].options.method,'POST');assert.equal(calls[0].options.body.get('csrf_token'),'offline-csrf');assert.equal(calls[0].options.body.get('action'),'save');
    api.handleFormSubmit(event);assert.equal(calls.length,1,'Already prevented/double submit does not repeat');
    const cancelled={target:form(),defaultPrevented:true,preventDefault(){throw Error('Cancelled confirmation intercepted');}};api.handleFormSubmit(cancelled);assert.equal(calls.length,1);
    const native={target:form('post','process_forgot_password.php'),defaultPrevented:false,preventDefault(){throw Error('Native authentication POST intercepted');}};api.handleFormSubmit(native);assert.equal(calls.length,1);
    const dialog={target:form('dialog'),defaultPrevented:false,preventDefault(){throw Error('Dialog submit intercepted');}};api.handleFormSubmit(dialog);
    const get={target:form('get'),defaultPrevented:false,preventDefault(){this.defaultPrevented=true;}};api.handleFormSubmit(get);assert.equal(calls[1].options.method,'GET');assert(!calls[1].url.searchParams.has('page_no'),'Filter resets server page');assert.equal(calls[1].options.body,undefined);
}
{
    const form=new Form();form.elements={action:{value:'create'},role:{value:'SUPER_ADMIN'},first_name:{value:'Test'},middle_name:{value:''},last_name:{value:'Admin'},username:{value:'fixture'},email:{value:'fixture@example.test'}};
    let confirmations=0;const win={confirm(){confirmations++;return false;}};const document={readyState:'loading',getElementById:()=>form,addEventListener(){}};
    vm.runInNewContext(fs.readFileSync(__dirname+'/../js/admin_management.js','utf8'),{window:win,document});win.initializeAdministrators();win.initializeAdministrators();assert.equal(form.listeners.submit.length,1);
    let cancelled=false;form.listeners.submit[0]({preventDefault(){cancelled=true;}});assert(cancelled);assert.equal(confirmations,1,'Super Admin creation confirmation preserved');form.elements.role.value='ADMIN';form.listeners.submit[0]({preventDefault(){throw Error('Admin unexpectedly cancelled');}});assert.equal(confirmations,1);
}
{
    const {api,calls}=setup();
    function linkEvent(href,settings={}) {
        const link=new Element();link.attrs.href=href;link.textContent='Next';link.closest=()=>settings.modal?link:null;link.matches=()=>!!settings.disabled;
        const event={button:0,target:{closest:()=>link},defaultPrevented:false,preventDefault(){this.defaultPrevented=true;},...settings};return event;
    }
    const page=linkEvent('app.php?page=security_logs&page_no=2');api.handleLinkClick(page);assert(page.defaultPrevented);assert.equal(calls.length,1);
    for(const event of [linkEvent('app.php?page=security_logs&view=1',{modal:true}),linkEvent('#login-sessions'),linkEvent('https://elsewhere.test/'),linkEvent('app.php?page=security_logs&page_no=2',{ctrlKey:true})]){api.handleLinkClick(event);assert(!event.defaultPrevented);}
    const disabled=linkEvent('app.php?page=security_logs&page_no=2',{disabled:true});api.handleLinkClick(disabled);assert(disabled.defaultPrevented);assert.equal(calls.length,1,'Disabled/modal/native links do not issue dashboard requests');
}
{
    const panel=new Element(),trigger=new Element(),input=new Element();input.reset=()=>{};panel.style={display:'none'};panel.querySelector=()=>input;panel.scrollIntoView=()=>{};
    const win={};const document={readyState:'loading',getElementById:()=>panel,querySelector:()=>({getBoundingClientRect:()=>({height:90})}),querySelectorAll:q=>q.includes('data-mp-toggle')?[trigger]:[],addEventListener(){}};
    vm.runInNewContext(fs.readFileSync(__dirname+'/../js/my_profile.js','utf8'),{window:win,document});win.initializeMyProfile();win.initializeMyProfile();assert.equal(trigger.listeners.click.length,1);
    trigger.listeners.click[0]({preventDefault(){}});assert.equal(panel.style.display,'block');assert.equal(panel.style.scrollMarginTop,'106px');trigger.listeners.click[0]({preventDefault(){}});assert.equal(panel.style.display,'none');
}
console.log('PASS: installation paths, document/member roots, relative scroll and short pages, tab resets, filter inheritance, CSRF/submitter POST payloads, cancelled/native/dialog forms, duplicate submissions, and idempotent Administrator confirmation.');
