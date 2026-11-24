import { createOpenStreetMap } from './openstreet-map';

const form = document.querySelector('[data-weather-url]');

if (form) {
    const latitude = document.getElementById('lat');
    const longitude = document.getElementById('lon');
    const coordinates = document.getElementById('coordinates');
    const weatherPanel = document.getElementById('weather');
    const mapPanel = document.getElementById('map');
    let freeMap;
    let map;
    let marker;
    let Marker;
    let weatherRequest;
    let weatherTimer;
    let mapReady = false;

    function currentLocation() {
        if (latitude.value.trim() === '' || longitude.value.trim() === '') return null;
        const lat = Number(latitude.value);
        const lon = Number(longitude.value);
        return Number.isFinite(lat) && Number.isFinite(lon) && lat >= -90 && lat <= 90 && lon >= -180 && lon <= 180 ? { lat, lon } : null;
    }

    function mapMessage(message) {
        mapPanel.replaceChildren();
        const text = document.createElement('p');
        text.setAttribute('role', 'status');
        text.textContent = message;
        mapPanel.appendChild(text);
    }

    async function loadWeather(lat, lon) {
        weatherRequest?.abort();
        const controller = new AbortController();
        weatherRequest = controller;
        weatherPanel.textContent = 'Loading weather…';
        const url = new URL(form.dataset.weatherUrl, window.location.origin);
        url.searchParams.set('lat', lat);
        url.searchParams.set('lon', lon);
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: controller.signal });
            const data = await response.json();
            if (!response.ok) {
                weatherPanel.textContent = response.status === 429 ? 'Too many weather requests. Wait a minute and try again.' : (data.message || 'Weather is currently unavailable.');
                return;
            }
            weatherPanel.textContent = `${data.place}: ${data.temperature}°C, ${data.description || 'Current conditions'}, humidity ${data.humidity}%.`;
        } catch (error) {
            if (error.name !== 'AbortError') weatherPanel.textContent = 'Weather is currently unavailable. Please try again later.';
        }
    }

    function setLocation(lat, lon, pan = false) {
        latitude.value = lat.toFixed(7);
        longitude.value = lon.toFixed(7);
        coordinates.textContent = `Latitude: ${lat.toFixed(5)}, Longitude: ${lon.toFixed(5)}`;
        freeMap?.select(lat, lon, pan);
        if (map && Marker) {
            if (marker) marker.map = null;
            marker = new Marker({ map, position: { lat, lng: lon }, title: 'Selected application location' });
            if (pan) map.panTo({ lat, lng: lon });
        }
        clearTimeout(weatherTimer);
        weatherRequest?.abort();
        weatherTimer = setTimeout(() => loadWeather(lat, lon), 350);
    }

    document.getElementById('update-location')?.addEventListener('click', () => {
        const point = currentLocation();
        if (!point) {
            coordinates.textContent = 'Enter latitude from -90 to 90 and longitude from -180 to 180.';
            return;
        }
        setLocation(point.lat, point.lon, true);
    });

    window.gm_authFailure = () => {
        mapReady = false;
        map = null;
        mapMessage('Google Maps could not authenticate. Check the browser key, enabled Maps API, billing, and allowed website. You can still enter coordinates manually.');
    };

    window.initMembershipMap = async () => {
        try {
            const { Map } = await google.maps.importLibrary('maps');
            ({ AdvancedMarkerElement: Marker } = await google.maps.importLibrary('marker'));
            const point = currentLocation() || { lat: 14.5995, lon: 120.9842 };
            mapPanel.replaceChildren();
            map = new Map(mapPanel, { center: { lat: point.lat, lng: point.lon }, zoom: 13, mapId: form.dataset.mapId || 'DEMO_MAP_ID', streetViewControl: false, mapTypeControl: false });
            map.addListener('click', event => {
                if (form.dataset.readonly !== 'true' && event.latLng) setLocation(event.latLng.lat(), event.latLng.lng());
            });
            mapReady = true;
            setLocation(point.lat, point.lon);
        } catch {
            mapMessage('Google Maps is currently unavailable. You can still enter coordinates manually.');
        }
    };

    const initialPoint = currentLocation();
    if (initialPoint) setLocation(initialPoint.lat, initialPoint.lon);
    if (form.dataset.mapProvider === 'google' && form.dataset.mapsKey) {
        const script = document.createElement('script');
        const url = new URL('https://maps.googleapis.com/maps/api/js');
        url.search = new URLSearchParams({ key: form.dataset.mapsKey, loading: 'async', callback: 'initMembershipMap', v: 'weekly', libraries: 'marker' });
        script.src = url.toString();
        script.async = true;
        script.onerror = () => mapMessage('Google Maps could not load. Check your connection or enter coordinates manually.');
        document.head.appendChild(script);
        setTimeout(() => { if (!mapReady) mapMessage('Google Maps is unavailable. Check the API key and connection, or enter coordinates manually.'); }, 15000);
    } else {
        const point = initialPoint || { lat: 14.5995, lon: 120.9842 };
        freeMap = createOpenStreetMap(mapPanel, point, (lat, lon) => setLocation(lat, lon), form.dataset.readonly === 'true');
    }

    form.addEventListener('submit', () => {
        const button = document.getElementById('submit-application');
        if (!button) return;
        button.disabled = true;
        button.textContent = 'Saving application…';
    });
    window.addEventListener('pageshow', () => {
        const button = document.getElementById('submit-application');
        if (!button) return;
        button.disabled = form.querySelectorAll('#membership_category_id option').length <= 1;
        button.textContent = 'Create membership application';
    });
}
