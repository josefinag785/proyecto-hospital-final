// Leaflet muestra el mapa; este código guarda los puntos que marca el conductor.
const campoRecorrido = document.getElementById('recorrido');

if (campoRecorrido) {
    const aviso = document.getElementById('estado-recorrido');
    const deshacer = document.getElementById('deshacer-punto');
    const limpiar = document.getElementById('limpiar-recorrido');
    deshacer.disabled = true;
    limpiar.disabled = true;

    if (typeof L === 'undefined') {
        aviso.textContent = 'No se pudo cargar el mapa. Revise su conexión a Internet; puede guardar las horas y el estado.';
    } else {
        const puntos = JSON.parse(campoRecorrido.value);
        const mapa = L.map('mapa-recorrido').setView([-32.5, -56], 6);

        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(mapa);

        const linea = L.polyline(puntos, {
            color: '#2563EB',
            weight: 4
        }).addTo(mapa);

        const marcas = L.layerGroup().addTo(mapa);

        function actualizar() {
            linea.setLatLngs(puntos);
            marcas.clearLayers();

            puntos.forEach((punto, indice) => {
                L.circleMarker(punto, {
                    radius: 4,
                    bubblingMouseEvents: false
                }).bindTooltip(String(indice + 1)).addTo(marcas);
            });

            campoRecorrido.value = JSON.stringify(puntos);
            deshacer.disabled = puntos.length === 0;
            limpiar.disabled = puntos.length === 0;
            aviso.textContent = puntos.length + ' puntos marcados. Pulse Guardar cambios para guardarlos.';
        }

        mapa.on('click', evento => {
            if (puntos.length >= 500) {
                aviso.textContent = 'El recorrido admite hasta 500 puntos.';
                return;
            }

            puntos.push([
                Number(evento.latlng.lat.toFixed(6)),
                Number(evento.latlng.lng.toFixed(6))
            ]);

            actualizar();
        });

        deshacer.addEventListener('click', () => {
            puntos.pop();
            actualizar();
        });

        limpiar.addEventListener('click', () => {
            puntos.length = 0;
            actualizar();
        });

        campoRecorrido.form.addEventListener('submit', evento => {
            if (puntos.length === 1) {
                evento.preventDefault();
                aviso.textContent = 'Marque al menos dos puntos, o borre el recorrido para guardar sin puntos.';
            }
        });

        actualizar();

        if (puntos.length > 1) {
            mapa.fitBounds(linea.getBounds(), {
                padding: [15, 15]
            });
        }
    }
}