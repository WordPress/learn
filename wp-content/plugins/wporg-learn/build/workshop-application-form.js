(window.wporgLearnPlugin=window.wporgLearnPlugin||[]).push([[2],{6:function(e,r,n){}}]),function(e){function r(r){for(var t,l,u=r[0],p=r[1],c=r[2],f=0,s=[];f<u.length;f++)l=u[f],Object.prototype.hasOwnProperty.call(o,l)&&o[l]&&s.push(o[l][0]),o[l]=0;for(t in p)Object.prototype.hasOwnProperty.call(p,t)&&(e[t]=p[t]);for(a&&a(r);s.length;)s.shift()();return i.push.apply(i,c||[]),n()}function n(){for(var e,r=0;r<i.length;r++){for(var n=i[r],t=!0,u=1;u<n.length;u++){var p=n[u];0!==o[p]&&(t=!1)}t&&(i.splice(r--,1),e=l(l.s=n[0]))}return e}var t={},o={4:0},i=[];function l(r){if(t[r])return t[r].exports;var n=t[r]={i:r,l:!1,exports:{}};return e[r].call(n.exports,n,n.exports,l),n.l=!0,n.exports}l.m=e,l.c=t,l.d=function(e,r,n){l.o(e,r)||Object.defineProperty(e,r,{enumerable:!0,get:n})},l.r=function(e){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(e,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(e,"__esModule",{value:!0})},l.t=function(e,r){if(1&r&&(e=l(e)),8&r)return e;if(4&r&&"object"==typeof e&&e&&e.__esModule)return e;var n=Object.create(null);if(l.r(n),Object.defineProperty(n,"default",{enumerable:!0,value:e}),2&r&&"string"!=typeof e)for(var t in e)l.d(n,t,function(r){return e[r]}.bind(null,t));return n},l.n=function(e){var r=e&&e.__esModule?function(){return e.default}:function(){return e};return l.d(r,"a",r),r},l.o=function(e,r){return Object.prototype.hasOwnProperty.call(e,r)},l.p="";var u=window.wporgLearnPlugin=window.wporgLearnPlugin||[],p=u.push.bind(u);u.push=r,u=u.slice();for(var c=0;c<u.length;c++)r(u[c]);var a=p;i.push([9,2]),n()}([function(e,r){!function(){e.exports=this.wp.i18n}()},function(e,r){!function(){e.exports=this.wp.blocks}()},function(e,r){!function(){e.exports=this.wp.element}()},,,function(e,r,n){},,,,function(e,r,n){"use strict";n.r(r);var t=n(1),o=n(0),i=n(2);n(5);n(6);Object(t.registerBlockType)("wporg-learn/workshop-application-form",{title:Object(o.__)("Workshop Application Form","wporg-learn"),description:Object(o.__)("Render a form for applying to present a workshop.","wporg-learn"),category:"widgets",icon:"smiley",supports:{html:!1},edit:function(e){var r=e.className;return Object(i.createElement)("div",{className:r},Object(i.createElement)("p",null,Object(o.__)("Workshop Application Form","wporg-learn")),Object(i.createElement)("p",null,Object(o.__)("This will render a form on the front end.","wporg-learn")))},save:function(){return null}})}]);