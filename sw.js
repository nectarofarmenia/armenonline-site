/* Nectar of Armenia: shows admin push notifications */
self.addEventListener("install", function () { self.skipWaiting(); });
self.addEventListener("activate", function (e) { e.waitUntil(self.clients.claim()); });
self.addEventListener("push", function (e) {
  e.waitUntil(fetch("/api/api.php?a=push_latest", { credentials: "include", cache: "no-store" }).then(function (r) { return r.json(); }).catch(function () { return {}; }).then(function (j) {
    var show = function (n) { var tt = String(n.title || ""); var ic = /Chef Apri ագենտ/.test(tt) ? "/img/agent-apri-192.png" : (/Nectar ագենտ/.test(tt) ? "/img/agent-nectar-192.png" : "/img/icon-192-green.png"); return self.registration.showNotification(n.title || "Nectar of Armenia", { body: n.body || "", icon: ic, badge: "/img/icon-192-green.png", tag: "nofa-" + (n.t || Date.now()), timestamp: n.t || Date.now(), data: { url: "/" + String(n.url || "Admin.dc.html").replace(/^\//, "") } }); };
    var Q = (j && j.q) || (j && j.n ? [j.n] : []); if (!Q.length) { return show({ title: "Nectar of Armenia", body: "Նոր ծանուցում ադմինում", url: "Admin.dc.html" }); }
    // show every notice not shown yet (several can arrive together), newest last
    return caches.open("nofa-sw").then(function (c) { return c.match("/__pushlast").then(function (r) { return r ? r.text() : "0"; }).then(function (lt) {
      var last = +lt || 0, nw = Q.filter(function (n) { return (n.t || 0) > last; }).slice(0, 4).reverse(); if (!nw.length) { nw = [Q[0]]; }
      return c.put("/__pushlast", new Response(String(Q[0].t || Date.now()))).then(function () { return Promise.all(nw.map(show)); });
    }); }).catch(function () { return show(Q[0]); });
  }));
});
self.addEventListener("notificationclick", function (e) {
  e.notification.close(); var url = (e.notification.data && e.notification.data.url) || "/Admin.dc.html";
  e.waitUntil(self.clients.matchAll({ type: "window", includeUncontrolled: true }).then(function (list) { for (var i = 0; i < list.length; i++) { if (/Admin\.dc\.html/.test(list[i].url)) { return list[i].focus(); } } return self.clients.openWindow(url); }));
});
