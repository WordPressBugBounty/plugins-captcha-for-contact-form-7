(function () {
    var KEY = f12_client_data.key;
    var SITE = location.hostname;
    var V = "2025.09.1"; // version pinning
    var s = document.createElement('script');
    s.src = f12_client_data.url + "/client.js?k=" + encodeURIComponent(KEY) + "&v=" + encodeURIComponent(V) + "&site=" + encodeURIComponent(SITE);
    s.async = true;
    s.crossOrigin = "anonymous";
    document.head.appendChild(s);
})();