// A small map picker using public OpenStreetMap raster tiles and browser caching.
const TILE_SIZE = 256;
const MAX_LATITUDE = 85.05112878;
function project(lat, lon, zoom) {
    const size = TILE_SIZE * 2 ** zoom;
    const sine = Math.sin(Math.max(-MAX_LATITUDE, Math.min(MAX_LATITUDE, lat)) * Math.PI / 180);
    return { x: (lon + 180) / 360 * size, y: (0.5 - Math.log((1 + sine) / (1 - sine)) / (4 * Math.PI)) * size };
}
function unproject(x, y, zoom) {
    const size = TILE_SIZE * 2 ** zoom;
    return { lat: Math.atan(Math.sinh(Math.PI * (1 - 2 * Math.max(0, Math.min(size, y)) / size))) * 180 / Math.PI, lon: ((x / size * 360) % 360 + 360) % 360 - 180 };
}
export function createOpenStreetMap(panel, initialPoint, onSelect, readOnly = false) {
    let zoom = 13;
    let center = { ...initialPoint };
    let selected = { ...initialPoint };
    let drag;
    const tiles = new Map();
    panel.replaceChildren();
    panel.classList.add('osm-map');
    panel.tabIndex = 0;
    panel.setAttribute('aria-label', readOnly ? 'Office map. Arrow keys pan; plus and minus zoom.' : 'Location map. Click to select a point. Arrow keys pan; Enter selects the center; plus and minus zoom.');
    const layer = document.createElement('div');
    layer.className = 'osm-tile-layer';
    layer.setAttribute('aria-hidden', 'true');
    const pin = document.createElement('span');
    pin.className = 'osm-pin';
    pin.setAttribute('aria-hidden', 'true');
    const controls = document.createElement('div');
    controls.className = 'osm-controls';
    const status = document.createElement('span');
    status.className = 'osm-status';
    status.setAttribute('role', 'status');
    const credit = document.createElement('a');
    credit.href = 'https://www.openstreetmap.org/copyright';
    credit.target = '_blank';
    credit.rel = 'noopener';
    credit.className = 'osm-credit';
    credit.textContent = '© OpenStreetMap contributors';
    function changeZoom(delta) {
        zoom = Math.max(2, Math.min(18, zoom + delta));
        render();
    }
    for (const [label, text, delta] of [['Zoom in', '+', 1], ['Zoom out', '−', -1]]) {
        const button = document.createElement('button');
        button.type = 'button';
        button.setAttribute('aria-label', label);
        button.textContent = text;
        button.addEventListener('click', () => changeZoom(delta));
        controls.appendChild(button);
    }
    const recenter = document.createElement('button');
    recenter.type = 'button';
    recenter.textContent = '⌖';
    recenter.setAttribute('aria-label', 'Show selected location');
    recenter.addEventListener('click', () => { center = { ...selected }; render(); });
    controls.appendChild(recenter);
    panel.append(layer, pin, controls, status, credit);
    function render() {
        const width = panel.clientWidth;
        const height = panel.clientHeight;
        if (!width || !height) return;
        const position = project(center.lat, center.lon, zoom);
        const left = position.x - width / 2;
        const top = position.y - height / 2;
        const count = 2 ** zoom;
        const visible = new Set();
        for (let y = Math.floor(top / TILE_SIZE); y <= Math.floor((top + height) / TILE_SIZE); y++) {
            if (y < 0 || y >= count) continue;
            for (let x = Math.floor(left / TILE_SIZE); x <= Math.floor((left + width) / TILE_SIZE); x++) {
                const key = `${zoom}/${x}/${y}`;
                visible.add(key);
                let tile = tiles.get(key);
                if (!tile) {
                    tile = document.createElement('img');
                    tile.alt = '';
                    tile.draggable = false;
                    tile.width = TILE_SIZE;
                    tile.height = TILE_SIZE;
                    tile.referrerPolicy = 'strict-origin-when-cross-origin';
                    tile.addEventListener('error', () => { status.textContent = 'Some map tiles could not load. You can still enter coordinates below.'; });
                    tile.src = `https://tile.openstreetmap.org/${zoom}/${((x % count) + count) % count}/${y}.png`;
                    layer.appendChild(tile);
                    tiles.set(key, tile);
                }
                tile.style.transform = `translate(${Math.round(x * TILE_SIZE - left)}px,${Math.round(y * TILE_SIZE - top)}px)`;
            }
        }
        for (const [key, tile] of tiles) {
            if (!visible.has(key)) { tile.remove(); tiles.delete(key); }
        }
        const point = project(selected.lat, selected.lon, zoom);
        const world = TILE_SIZE * count;
        let dx = point.x - position.x;
        dx -= Math.round(dx / world) * world;
        pin.style.left = `${width / 2 + dx}px`;
        pin.style.top = `${height / 2 + point.y - position.y}px`;
    }
    function pointAt(clientX, clientY) {
        const box = panel.getBoundingClientRect();
        const position = project(center.lat, center.lon, zoom);
        return unproject(position.x + clientX - box.left - box.width / 2, position.y + clientY - box.top - box.height / 2, zoom);
    }
    panel.addEventListener('pointerdown', event => {
        if (event.button !== 0 || event.target.closest('button,a')) return;
        drag = { x: event.clientX, y: event.clientY, position: project(center.lat, center.lon, zoom), moved: false };
        panel.setPointerCapture(event.pointerId);
        panel.focus({ preventScroll: true });
    });
    panel.addEventListener('pointermove', event => {
        if (!drag) return;
        const dx = event.clientX - drag.x;
        const dy = event.clientY - drag.y;
        if (Math.abs(dx) + Math.abs(dy) > 5) drag.moved = true;
        if (drag.moved) { center = unproject(drag.position.x - dx, drag.position.y - dy, zoom); render(); }
    });
    panel.addEventListener('pointerup', event => {
        if (!drag) return;
        const choose = !drag.moved && !readOnly;
        drag = null;
        if (choose) { const point = pointAt(event.clientX, event.clientY); onSelect(point.lat, point.lon); }
    });
    panel.addEventListener('pointercancel', () => { drag = null; });
    panel.addEventListener('keydown', event => {
        if (event.target !== panel) return;
        const directions = { ArrowLeft: [-80, 0], ArrowRight: [80, 0], ArrowUp: [0, -80], ArrowDown: [0, 80] };
        if (directions[event.key]) {
            event.preventDefault();
            const point = project(center.lat, center.lon, zoom);
            const [dx, dy] = directions[event.key];
            center = unproject(point.x + dx, point.y + dy, zoom);
            render();
        } else if (event.key === '+' || event.key === '=') { event.preventDefault(); changeZoom(1); }
        else if (event.key === '-') { event.preventDefault(); changeZoom(-1); }
        else if (event.key === 'Enter' && !readOnly) { event.preventDefault(); onSelect(center.lat, center.lon); }
    });
    new ResizeObserver(render).observe(panel);
    render();
    return {
        select(lat, lon, pan = false) {
            selected = { lat, lon };
            if (pan) center = { ...selected };
            render();
        },
    };
}
