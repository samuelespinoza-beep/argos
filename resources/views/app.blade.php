<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Sin Referer al abrir ofertas: la analitica de cuponidad.pe guarda "http://localhost:8070" en la
         cookie ___nrbic y su WAF bloquea (403) toda visita siguiente que la lleve. Tampoco le contamos
         a la competencia la URL del panel. --}}
    <meta name="referrer" content="no-referrer">
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700;900&display=swap" rel="stylesheet">
    @vite('resources/js/app.js')
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
