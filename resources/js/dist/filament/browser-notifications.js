/*
<COPYRIGHT>

    Copyright © 2016-2026, Canyon GBS LLC. All rights reserved.

    Canyon GBS Common is licensed under the Elastic License 2.0. For more details,
    see https://github.com/canyongbs/common/blob/main/LICENSE.

    Notice:

    - You may not provide the software to third parties as a hosted or managed
      service, where the service provides users with access to any substantial set of
      the features or functionality of the software.
    - You may not move, change, disable, or circumvent the license key functionality
      in the software, and you may not remove or obscure any functionality in the
      software that is protected by the license key.
    - You may not alter, remove, or obscure any licensing, copyright, or other notices
      of the licensor in the software. Any use of the licensor’s trademarks is subject
      to applicable law.
    - Canyon GBS LLC respects the intellectual property rights of others and expects the
      same in return. Canyon GBS™ and Canyon GBS Common are registered trademarks of
      Canyon GBS LLC, and we are committed to enforcing and protecting our trademarks
      vigorously.
    - The software solution, including services, infrastructure, and code, is offered as a
      Software as a Service (SaaS) by Canyon GBS LLC.
    - Use of this software implies agreement to the license terms and conditions as stated
      in the Elastic License 2.0.

    For more information or inquiries please visit our website at
    https://www.canyongbs.com or contact us via email at legal@canyongbs.com.

</COPYRIGHT>
*/
var o=".fi-no-notification-unread-ctn",r=".database-notifications.ctn",c=new Set,u=!1;function a(){return typeof window<"u"&&"Notification"in window}function l(t){let e=t.getAttribute("wire:key")??"";return e.endsWith(r)?e.slice(0,-r.length):null}function w(t){return t.querySelector('[class*="fi-no-notification-title"]')?.textContent?.trim()||null}function N(t){return t.querySelector('[class*="fi-no-notification-body"]')?.textContent?.trim()||""}function m(t){return t.querySelector("a[href]")?.getAttribute("href")||null}function y(t){let e=w(t);if(!e)return;let n=m(t),i=new Notification(e,{body:N(t),icon:"/favicon.ico"});i.addEventListener("click",()=>{window.focus(),n&&(window.location.href=n),i.close()})}function s(t){let e=l(t);e===null||c.has(e)||(c.add(e),u&&Notification.permission==="granted"&&y(t))}function d(t){t instanceof Element&&(t.matches?.(o)&&s(t),t.querySelectorAll?.(o).forEach(s))}function b(){if(Notification.permission!=="default")return;let t=()=>{window.removeEventListener("click",t),window.removeEventListener("keydown",t),Notification.permission==="default"&&Notification.requestPermission()};window.addEventListener("click",t,{once:!0}),window.addEventListener("keydown",t,{once:!0})}function f(){if(!a())return;d(document.body),u=!0,b(),new MutationObserver(e=>{for(let n of e)n.addedNodes.forEach(i=>d(i))}).observe(document.body,{childList:!0,subtree:!0})}typeof document<"u"&&(document.readyState==="loading"?document.addEventListener("DOMContentLoaded",f,{once:!0}):f());
