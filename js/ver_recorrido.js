// Esta vista muestra el recorrido que ya guardó el conductor.
const recorridoGuardado = document.getElementById('recorrido-guardado');

if (recorridoGuardado) {
    const aviso = document.getElementById('estado-recorrido');

    if (typeof L === 'undefined') {
        aviso.textContent = 'No se pudo cargar el mapa. Revise su conexión a Internet.';
    } else {
        const puntos = JSON.parse(recorridoGuardado.value);
        const mapa = L.map('mapa-recorrido').setView([-32.5, -56], 6);

        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(mapa);

        const linea = L.polyline(puntos, {
            color: '#2563EB',
            weight: 4
        }).addTo(mapa);

        L.circleMarker(puntos[0])
            .bindTooltip('Inicio')
            .addTo(mapa);

        L.circleMarker(puntos[puntos.length - 1])
            .bindTooltip('Final')
            .addTo(mapa);

        mapa.fitBounds(linea.getBounds(), {
            padding: [15, 15]
        });

        aviso.textContent = 'Recorrido guardado por el conductor.';
    }
}