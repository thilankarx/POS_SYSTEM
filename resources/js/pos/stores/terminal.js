import { defineStore } from 'pinia';
import { getItem, setItem, removeItem } from './storage.js';

function readTerminal() {
    const raw = getItem('terminal');
    return raw ? JSON.parse(raw) : null;
}

export const useTerminalStore = defineStore('terminal', {
    state: () => ({
        terminal: readTerminal(),
    }),
    getters: {
        isSelected: (state) => Boolean(state.terminal),
    },
    actions: {
        select(terminal) {
            this.terminal = terminal;
            setItem('terminal', JSON.stringify(terminal));
        },
        clear() {
            this.terminal = null;
            removeItem('terminal');
        },
    },
});
