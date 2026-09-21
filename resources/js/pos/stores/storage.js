const PREFIX = 'pos:';

export function getItem(key) {
    return localStorage.getItem(PREFIX + key);
}

export function setItem(key, value) {
    localStorage.setItem(PREFIX + key, value);
}

export function removeItem(key) {
    localStorage.removeItem(PREFIX + key);
}
