import{c as t,A as s,b as i,d as r,e as u}from"./silentshield-admin.js";import{w as a}from"./silentshield-vendor-lib-BOYiOmBi.js";/**
 * @license lucide-react v0.462.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const l=t("Activity",[["path",{d:"M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2",key:"169zse"}]]);function o(e=30){return a({queryKey:["analytics","summary",e],queryFn:()=>s(e)})}function m(e=30){return a({queryKey:["analytics","timeline",e],queryFn:()=>i(e)})}function A(e=30){return a({queryKey:["analytics","reasons",e],queryFn:()=>r(e)})}function q(e={}){return a({queryKey:["analytics","log",e],queryFn:()=>u(e),placeholderData:n=>n})}export{l as A,m as a,A as b,q as c,o as u};
