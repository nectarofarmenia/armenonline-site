/* Nectar of Armenia: shows admin push notifications */
self.addEventListener("install", function () { self.skipWaiting(); });
self.addEventListener("activate", function (e) { e.waitUntil(self.clients.claim()); });
self.addEventListener("push", function (e) {
  e.waitUntil(fetch("/api/api.php?a=push_latest", { credentials: "include", cache: "no-store" }).then(function (r) { return r.json(); }).catch(function () { return {}; }).then(function (j) {
    var n = (j && j.n) || { title: "Nectar of Armenia", body: "Նոր ծանուցում ադմինում", url: "Admin.dc.html" };
    return self.registration.showNotification(n.title || "Nectar of Armenia", { body: n.body || "", icon: "/img/icon-192-green.png", badge: "/img/icon-192-green.png", tag: "nofa-" + (n.t || Date.now()), data: { url: "/" + String(n.url || "Admin.dc.html").replace(/^\//, "") } });
  }));
});
self.addEventListener("notificationclick", function (e) {
  e.notification.close(); var url = (e.notification.data && e.notification.data.url) || "/Admin.dc.html";
  e.waitUntil(self.clients.matchAll({ type: "window", includeUncontrolled: true }).then(function (list) { for (var i = 0; i < list.length; i++) { if (/Admin\.dc\.html/.test(list[i].url)) { return list[i].focus(); } } return self.clients.openWindow(url); }));
});
