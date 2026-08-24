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
var d=".fi-no-notification-unread-ctn",u=".database-notifications.ctn";var o=new Set,l=!0;function w(){return typeof window<"u"&&"Notification"in window}function N(t){let e=t.getAttribute("wire:key")??"";return e.endsWith(u)?e.slice(0,-u.length):null}function m(t){return t.querySelector('[class*="fi-no-notification-title"]')?.textContent?.trim()||null}function y(t){return t.querySelector('[class*="fi-no-notification-body"]')?.textContent?.trim()||""}function E(t){return t.querySelector("a[href]")?.getAttribute("href")||null}function b(t){let e=m(t),n=y(t);if(!e&&!n)return;let i=e||document.title||"Notification",c=E(t),s=new Notification(i,{body:n,icon:"/favicon.ico"});s.addEventListener("click",()=>{window.focus(),c&&(window.location.href=c),s.close()})}function f(t){let e=N(t);if(!(e===null||o.has(e))){if(l){o.add(e);return}Notification.permission==="granted"&&(o.add(e),b(t))}}function r(t){t instanceof Element&&(t.matches?.(d)&&f(t),t.querySelectorAll?.(d).forEach(f))}function h(){if(Notification.permission!=="default")return;let t=()=>{window.removeEventListener("click",t),window.removeEventListener("keydown",t),Notification.permission==="default"&&Notification.requestPermission().then(e=>{e==="granted"&&r(document.body)})};window.addEventListener("click",t,{once:!0}),window.addEventListener("keydown",t,{once:!0})}function a(){if(!w())return;r(document.body),window.setTimeout(()=>{l=!1},3e3),h(),new MutationObserver(e=>{for(let n of e)n.addedNodes.forEach(i=>r(i))}).observe(document.body,{childList:!0,subtree:!0})}typeof document<"u"&&(document.readyState==="loading"?document.addEventListener("DOMContentLoaded",a,{once:!0}):a());
