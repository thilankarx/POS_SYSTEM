<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount, nextTick, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useCartStore } from '../stores/cart.js';
import { useTerminalStore } from '../stores/terminal.js';
import { useCatalogStore } from '../stores/catalog.js';
import { useAuthStore } from '../stores/auth.js';
import { useBusinessStore } from '../stores/business.js';
import { useCategoriesStore } from '../stores/categories.js';
import { useTablesStore } from '../stores/tables.js';
import { useConnectivityStore } from '../stores/connectivity.js';
import { apiFetch, ApiError } from '../api/client.js';
import BarcodeCapture from '../components/BarcodeCapture.vue';
import { formatMoney } from '../lib/money.js';
import { playSuccessBeep, playErrorBuzz } from '../lib/audio.js';

const router = useRouter();
const cart = useCartStore();
const terminalStore = useTerminalStore();
const catalog = useCatalogStore();
const authStore = useAuthStore();
const business = useBusinessStore();
const categories = useCategoriesStore();
const tables = useTablesStore();
const connectivity = useConnectivityStore();
const tableError = ref(null);
const search = ref('');
const lookupError = ref(null);
const looking = ref(false);
const searchResults = ref([]);
const activeCategoryId = ref(null);
const highlightedIndex = ref(0);
const searchInput = ref(null);
const gridContainer = ref(null);
const showParkedDrawer = ref(false);

const otherTerminalCarts = ref([]);
const couponCode = ref('');
const couponBusy = ref(false);
const showCouponField = ref(false);
const customerQuery = ref('');
const customerResults = ref([]);
const customerSearching = ref(false);
const customerQueryInput = ref(null);
const showShortcuts = ref(false);
const mobilePane = ref('catalog');

// Driven by the connectivity store's server heartbeat (App.vue owns its
// init + the window online/offline listeners), not this view's own
// navigator.onLine read -- so it also flips when the server PC is down
// while the register's own network is fine.
const isOffline = computed(() => !connectivity.online);

// One place for transient feedback -- drawer/kitchen/table actions used to
// each drop a stray <p> between the header and the content, shifting the
// whole layout. Now they stack as dismissible toasts in a fixed corner.
const toasts = ref([]);
let toastSeq = 0;
function pushToast(message, type = 'info', ttl = 3600) {
    if (!message) {
        return;
    }
    const id = ++toastSeq;
    toasts.value.push({ id, message, type });
    setTimeout(() => dismissToast(id), ttl);
}
function dismissToast(id) {
    toasts.value = toasts.value.filter((toast) => toast.id !== id);
}

// Pre-discount line total for the register display -- the authoritative,
// promotion-and-tax-aware figure still comes from cart.totals in the footer.
function lineTotal(line) {
    return Number(line.unit_price) * Number(line.quantity);
}

const cartLineCount = computed(() => cart.cart?.lines.length ?? 0);

let idleTimer = null;
function startIdleTimer() {
    clearTimeout(idleTimer);
    idleTimer = setTimeout(() => {
        if (cart.cart) {
            park().finally(() => signOut());
        } else {
            signOut();
        }
    }, 5 * 60 * 1000); // 5 minutes
}
function resetIdleTimer() {
    startIdleTimer();
}

watch(searchResults, () => {
    highlightedIndex.value = 0;
});

// Refreshes the floor whenever the register returns to the no-cart state
// (park, discard, or a completed sale routing back here) so a table's
// available/occupied tile reflects what just happened.
watch(
    () => cart.cart,
    (current) => {
        if (!current) {
            mobilePane.value = 'catalog';
        }
        if (!current && business.isRestaurantMode && terminalStore.terminal) {
            tables.fetchForLocation(terminalStore.terminal.stock_location_id);
        }
    },
);

async function focusSearch() {
    await nextTick();
    searchInput.value?.focus();
}

onMounted(async () => {
    await cart.refreshParked();
    await refreshOtherTerminalCarts();
    if (business.isRestaurantMode && terminalStore.terminal) {
        await tables.fetchForLocation(terminalStore.terminal.stock_location_id);
    }
    if (cart.cart) {
        // Picks up any kitchen_sent/kitchen_prepared changes made on the
        // kitchen display while this register was elsewhere (e.g. the
        // cashier tapped over to /kitchen and back) -- there's no other
        // way this device would learn about it before the cashier's next
        // action.
        await cart.refreshTotals();
        await focusSearch();
    }
    window.addEventListener('keydown', onRegisterKeydown);
    document.addEventListener('mousemove', resetIdleTimer);
    document.addEventListener('keydown', resetIdleTimer);
    document.addEventListener('touchstart', resetIdleTimer);
    startIdleTimer();
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onRegisterKeydown);
    document.removeEventListener('mousemove', resetIdleTimer);
    document.removeEventListener('keydown', resetIdleTimer);
    document.removeEventListener('touchstart', resetIdleTimer);
    clearTimeout(idleTimer);
});

// F-keys never produce characters, so no input-target filtering is
// needed here (unlike digit-key shortcuts, which must never leak into
// a text field a cashier is typing into).
function onRegisterKeydown(event) {
    if (event.key === 'Escape' && priceOptions.value.length) {
        event.preventDefault();
        cancelPricePicker();
    } else if (event.key === 'Enter' && !cart.cart && !business.isRestaurantMode) {
        event.preventDefault();
        startSale();
    } else if (event.key === 'Escape') {
        event.preventDefault();
        if (showShortcuts.value) {
            showShortcuts.value = false;
        } else if (showParkedDrawer.value) {
            showParkedDrawer.value = false;
        } else if (searchResults.value.length) {
            clearSearch();
        } else if (search.value) {
            clearSearch();
        } else if (cart.cart) {
            discard();
        }
    } else if (event.key === '?' && !['INPUT', 'TEXTAREA'].includes(event.target.tagName)) {
        event.preventDefault();
        showShortcuts.value = !showShortcuts.value;
    } else if (event.key === 'F6') {
        event.preventDefault();
        customerQueryInput.value?.focus();
    } else if (event.key === '+' || event.key === '=') {
        if (cart.cart?.lines.length && !['INPUT', 'TEXTAREA'].includes(event.target.tagName)) {
            event.preventDefault();
            const lastLine = cart.cart.lines[cart.cart.lines.length - 1];
            adjustLineQuantity(lastLine, lastLine.quantity + 1);
        }
    } else if (event.key === '-' || event.key === '_') {
        if (cart.cart?.lines.length && !['INPUT', 'TEXTAREA'].includes(event.target.tagName)) {
            event.preventDefault();
            const lastLine = cart.cart.lines[cart.cart.lines.length - 1];
            if (lastLine.quantity > 1) {
                adjustLineQuantity(lastLine, lastLine.quantity - 1);
            } else {
                cart.decrementLine(lastLine);
            }
        }
    } else if (event.key === 'F2') {
        event.preventDefault();
        if (cart.cart) {
            park();
        }
    } else if (event.key === 'F4') {
        event.preventDefault();
        if (cart.cart && cart.cart.lines.length > 0) {
            if (cart.cart.sale_type === 'quote') {
                completeQuote();
            } else if (authStore.user?.can_checkout) {
                goToPayment();
            } else {
                park();
            }
        }
    } else if (event.key === 'F9') {
        event.preventDefault();
        if (terminalStore.terminal?.has_printer && authStore.user?.can_checkout) {
            openDrawer();
        }
    }
}

async function refreshOtherTerminalCarts() {
    try {
        otherTerminalCarts.value = await cart.listOtherTerminalCarts(terminalStore.terminal.id);
    } catch {
        otherTerminalCarts.value = [];
    }
}

const totalParked = computed(() => cart.parked.length + otherTerminalCarts.value.length);

async function resumeOtherTerminalCart(otherCart) {
    await cart.resumeFromOtherTerminal(otherCart.client_uuid, terminalStore.terminal.id);
}

async function deleteParkedCart(parkedCart) {
    if (!window.confirm('Delete this parked sale? This cannot be undone.')) {
        return;
    }
    await cart.deleteParked(parkedCart.client_uuid);
}

async function deleteOtherTerminalCart(otherCart) {
    if (!window.confirm('Delete this parked sale? This cannot be undone.')) {
        return;
    }
    await cart.abandonOtherTerminalCart(otherCart.id);
    await refreshOtherTerminalCarts();
}

async function applyCoupon() {
    if (!couponCode.value.trim()) {
        return;
    }
    couponBusy.value = true;
    try {
        await cart.applyCoupon(couponCode.value.trim());
        couponCode.value = '';
    } finally {
        couponBusy.value = false;
    }
}

async function removeCoupon() {
    couponBusy.value = true;
    try {
        await cart.removeCoupon();
    } finally {
        couponBusy.value = false;
    }
}

let customerSearchTimer = null;
function onCustomerQueryInput() {
    clearTimeout(customerSearchTimer);
    if (!customerQuery.value.trim()) {
        customerResults.value = [];
        return;
    }
    customerSearchTimer = setTimeout(async () => {
        customerSearching.value = true;
        try {
            customerResults.value = await cart.searchCustomers(customerQuery.value.trim());
        } finally {
            customerSearching.value = false;
        }
    }, 300);
}

async function attachCustomer(customer) {
    await cart.attachCustomer(customer);
    customerQuery.value = '';
    customerResults.value = [];
}

async function removeCustomer() {
    await cart.removeCustomer();
}

const roughSubtotal = computed(() => {
    if (!cart.cart) {
        return 0;
    }
    return cart.cart.lines.reduce((sum, line) => sum + Number(line.unit_price) * Number(line.quantity), 0);
});

async function ensureCart() {
    if (!cart.cart) {
        await cart.startNewSale(terminalStore.terminal);
    }
}

function locationQueryParam() {
    return business.isHardwareMode && terminalStore.terminal
        ? `stock_location_id=${terminalStore.terminal.stock_location_id}`
        : '';
}

// Old stock and a newly-arrived, differently-priced batch of the same item
// can sit on the shelf at once. Most items never hit this (single lot, or no
// lots at all) -- fetchLotPriceOptions only returns something when 2+
// in-stock lots genuinely resolve to different prices, so the common case
// stays a zero-click add. Any failure (offline, network error) is treated
// the same as "no choice needed" -- this check must never block a sale.
const priceOptions = ref([]);
const pendingPriceItem = ref(null);
const pendingPriceQuantity = ref('1');
const pricePickerBusy = ref(false);
const pricePickerError = ref(null);
const selectedPriceLotId = ref(null);

async function fetchLotPriceOptions(item) {
    // has_multiple_prices comes from the catalog/barcode/search response
    // (computed in bulk server-side) -- skip the network round trip
    // entirely for the vast majority of items that don't need it.
    if (!item.moves_stock || !item.has_multiple_prices) {
        return null;
    }
    try {
        const res = await apiFetch(`/items/${item.id}/lot-prices`);
        if (!res.data.length) {
            return null;
        }
        const options = res.data.map((lot) => ({
            stock_lot_id: lot.stock_lot_id,
            lot_number: lot.lot_number,
            expires_on: lot.expires_on,
            price: lot.selling_price,
        }));
        const distinctPrices = new Set(options.map((option) => option.price));
        return distinctPrices.size >= 2 ? options : null;
    } catch {
        return null;
    }
}

async function addItemWithPriceCheck(item, quantity = '1') {
    const options = await fetchLotPriceOptions(item);
    if (!options) {
        await cart.addLine(item, quantity);
        return;
    }
    pendingPriceItem.value = item;
    pendingPriceQuantity.value = quantity;
    pricePickerError.value = null;
    priceOptions.value = options;
}

async function choosePriceOption(option) {
    if (pricePickerBusy.value) {
        return;
    }

    const item = pendingPriceItem.value;
    const quantity = pendingPriceQuantity.value;
    if (!item) {
        cancelPricePicker();
        return;
    }

    pricePickerBusy.value = true;
    selectedPriceLotId.value = option.stock_lot_id;
    pricePickerError.value = null;

    try {
        priceOptions.value = [];
        pendingPriceItem.value = null;
        await cart.addLine(item, quantity, option.stock_lot_id, option.price);
        if (cart.cart?.lastError) {
            pushToast(cart.cart.lastError, 'error', 6000);
        }
        await focusSearch();
    } catch (error) {
        pushToast(error instanceof ApiError
            ? error.message
            : error?.message || 'This price could not be added. Please try again.', 'error', 6000);
    } finally {
        pricePickerBusy.value = false;
        selectedPriceLotId.value = null;
    }
}

function cancelPricePicker() {
    if (pricePickerBusy.value) {
        return;
    }
    priceOptions.value = [];
    pendingPriceItem.value = null;
    pricePickerError.value = null;
    selectedPriceLotId.value = null;
}

async function lookupBarcode(code) {
    lookupError.value = null;
    await ensureCart();
    looking.value = true;
    try {
        const suffix = locationQueryParam();
        const res = await apiFetch(`/items/barcode/${encodeURIComponent(code)}${suffix ? `?${suffix}` : ''}`);
        playSuccessBeep();
        await addItemWithPriceCheck(res.data);
    } catch (e) {
        // Rate-limited: the scan did reach the server, it's just asking us
        // to slow down. Don't fall back to the cached catalog (that would
        // mask a real price/stock change) and don't say "not found" —
        // just tell the cashier to re-scan in a moment.
        if (e instanceof ApiError && e.isRateLimited) {
            playErrorBuzz();
            lookupError.value = 'Scanning too fast for the server — wait a second and re-scan.';
            setTimeout(() => { if (lookupError.value) clearSearch(); }, 2000);
            return;
        }
        // A network-level failure (offline) never reached the server at
        // all, so it's not a real "not found" — fall back to the item
        // catalog cached locally at login/reconnect. Any other error
        // (404, inactive item) is a genuine answer from the server.
        if (e instanceof ApiError && e.isNetworkError) {
            const cached = catalog.byBarcode(code);
            if (cached) {
                playSuccessBeep();
                await addItemWithPriceCheck(cached);
            } else {
                playErrorBuzz();
                lookupError.value = `No item found for "${code}" (offline — catalog may be out of date).`;
                setTimeout(() => { if (lookupError.value) clearSearch(); }, 2000);
            }
        } else {
            playErrorBuzz();
            lookupError.value = e instanceof ApiError ? e.message : `No item found for "${code}".`;
            setTimeout(() => { if (lookupError.value) clearSearch(); }, 2000);
        }
    } finally {
        looking.value = false;
        await focusSearch();
    }
}

// Kits are searched alongside items so a cashier can just type a kit's
// name/kit_number like anything else -- `kind` is a client-side-only tag
// (neither endpoint returns it) used to route the add and render the
// "Kit" badge.
async function addSearchMatch(match) {
    if (match.kind === 'kit') {
        await cart.addKit(match);
    } else {
        await addItemWithPriceCheck(match);
    }
}

async function resolveSearchMatches(matches, notFoundSuffix = '', term = '') {
    const label = term || search.value;
    if (matches.length === 0) {
        playErrorBuzz();
        lookupError.value = `No items match "${label}"${notFoundSuffix}.`;
        setTimeout(() => { if (lookupError.value) clearSearch(); }, 2000);
    } else if (matches.length === 1) {
        playSuccessBeep();
        await addSearchMatch(matches[0]);
        search.value = '';
        await focusSearch();
    } else {
        playSuccessBeep();
        searchResults.value = matches;
    }
}

async function runSearch(term) {
    // `term` is passed by onSearchEnter (it snapshots and clears the field
    // before this async work starts); the "Search" button calls runSearch()
    // with a MouseEvent, so fall back to the live field only for a string.
    const query = (typeof term === 'string' ? term : search.value).trim();
    if (!query) {
        return;
    }
    lookupError.value = null;
    searchResults.value = [];
    await ensureCart();
    looking.value = true;
    try {
        const suffix = locationQueryParam();
        const [itemsRes, kitsRes] = await Promise.all([
            apiFetch(`/items?q=${encodeURIComponent(query)}${suffix ? `&${suffix}` : ''}`),
            apiFetch(`/item-kits?q=${encodeURIComponent(query)}`),
        ]);
        const matches = [
            ...(itemsRes.data ?? []).map((item) => ({ ...item, kind: 'item' })),
            ...(kitsRes.data ?? []).map((kit) => ({ ...kit, kind: 'kit' })),
        ];
        await resolveSearchMatches(matches, '', query);
    } catch (e) {
        if (e instanceof ApiError && e.isRateLimited) {
            lookupError.value = 'Searching too fast for the server — wait a second and try again.';
        } else if (e instanceof ApiError && e.isNetworkError) {
            const matches = [
                ...catalog.search(query).map((item) => ({ ...item, kind: 'item' })),
                ...catalog.searchKits(query).map((kit) => ({ ...kit, kind: 'kit' })),
            ];
            await resolveSearchMatches(matches, ' (offline — catalog may be out of date)', query);
        } else {
            lookupError.value = e instanceof ApiError ? e.message : 'Search failed.';
        }
    } finally {
        looking.value = false;
    }
}

// Exact total (not an approximation of the split): summing components then
// applying the kit's own discount on top gives the right number regardless
// of how AddKitToCartAction later apportions a fixed discount per line.
function estimatedKitTotal(kit) {
    let total = 0;
    for (const component of kit.items ?? []) {
        const item = catalog.items.find((candidate) => candidate.id === component.item_id);
        if (item) {
            total += Number(item.unit_price) * Number(component.quantity);
        }
    }
    if (kit.price_option !== 'components') {
        total = kit.discount_type === 'percent'
            ? total * (1 - Number(kit.discount_value) / 100)
            : Math.max(0, total - Number(kit.discount_value));
    }
    return total;
}

// Enter routes here instead of straight to runSearch() so that once a
// multi-match dropdown is showing, a second Enter commits the highlighted
// row directly — "Enter adds the top match" without reaching for the mouse.
//
// A barcode scanner types the whole code then presses Enter within a few
// ms. runSearch() is async (awaits ensureCart() + API), so a second scan
// landing mid-lookup would otherwise append its digits onto the still-full
// field ("1234512345") and the fuzzy lookup for that string finds nothing.
// Snapshot the term and blank the field synchronously here — the DOM node
// too, not just the ref, because v-model reads the value back from the node
// and Vue hasn't flushed the ref yet when the next scan's keystrokes land.
// Lookups are queued so two quick scans don't both run ensureCart() (which
// would create duplicate carts) and keep their line-adds in scan order.
let searchQueue = Promise.resolve();

async function onSearchEnter() {
    const typed = search.value.trim();
    if (searchInput.value) {
        searchInput.value.value = '';
    }
    search.value = '';

    if (searchResults.value.length) {
        const picked = searchResults.value[highlightedIndex.value] ?? searchResults.value[0];
        searchQueue = searchQueue.then(() => pickSearchResult(picked)).catch(() => {});
        return;
    }
    if (!typed) {
        return;
    }
    searchQueue = searchQueue.then(() => runSearch(typed)).catch(() => {});
}

function moveHighlight(delta) {
    if (!searchResults.value.length) {
        return;
    }
    const count = searchResults.value.length;
    highlightedIndex.value = (highlightedIndex.value + delta + count) % count;
}

function clearSearch() {
    search.value = '';
    searchResults.value = [];
    lookupError.value = null;
}

async function pickSearchResult(result) {
    await addSearchMatch(result);
    searchResults.value = [];
    search.value = '';
    await focusSearch();
}

// Instant, client-side narrowing of the persistent product grid from the
// already-cached catalog — separate from runSearch()'s server round trip,
// which stays reserved for the barcode-precise / disambiguation-dropdown
// path (Enter or the Search button).
const gridItems = computed(() => {
    let items = catalog.items;
    if (activeCategoryId.value !== null) {
        items = items.filter((item) => item.category_id === activeCategoryId.value);
    }
    const needle = search.value.trim().toLowerCase();
    if (needle) {
        items = items.filter(
            (item) => item.name.toLowerCase().includes(needle) || item.sku.toLowerCase().includes(needle),
        );
    }
    return items;
});

async function addFromGrid(item) {
    await ensureCart();
    await addItemWithPriceCheck(item);
    await focusSearch();
}

// Reads the grid's *live* column count (it's responsive:
// grid-cols-2/sm:3/lg:4) rather than hardcoding one, and queries the
// container's current button children at keydown time instead of
// maintaining a separate refs array that could drift out of sync when
// gridItems is filtered down by category/search.
function moveGridFocus(rowDelta, colDelta) {
    const container = gridContainer.value;
    if (!container) {
        return;
    }
    const tiles = [...container.querySelectorAll(':scope > button')];
    const currentIndex = tiles.indexOf(document.activeElement);
    if (currentIndex === -1) {
        return;
    }
    const columns = getComputedStyle(container).gridTemplateColumns.split(' ').length;
    const delta = rowDelta * columns + colDelta;
    const nextIndex = Math.min(Math.max(currentIndex + delta, 0), tiles.length - 1);
    tiles[nextIndex]?.focus();
}

const avatarColors = ['bg-emerald-700', 'bg-sky-700', 'bg-amber-700', 'bg-violet-700', 'bg-rose-700', 'bg-cyan-700'];
function avatarColor(id) {
    return avatarColors[id % avatarColors.length];
}

function initials(name) {
    return (name || '?').trim().slice(0, 2).toUpperCase();
}

function signOut() {
    authStore.logout();
    router.push({ name: 'login' });
}

async function startSale() {
    await cart.startNewSale(terminalStore.terminal);
    await focusSearch();
}

async function startQuote() {
    await cart.startNewSale(terminalStore.terminal, 'quote');
    await focusSearch();
}

// An available tile opens a fresh cart against that table. An occupied
// tile looks up whatever order is parked there and resumes it -- the same
// mechanic already used to pick up a suspended cart parked by a different
// terminal (resumeFromOtherTerminal). Only a *suspended* cart is
// discoverable this way, so an order still active elsewhere is never
// silently reattributed.
async function selectTable(table) {
    tableError.value = null;

    if (table.status === 'available') {
        await cart.startNewSale(terminalStore.terminal, 'pos', table);
        await focusSearch();
        return;
    }

    try {
        const openCart = await tables.findOpenCart(table.id);
        if (!openCart) {
            // No *suspended* cart exists for this table yet -- either its
            // order is still being actively taken on another register (not
            // parked here yet), or the table is occupied without ever having
            // gone through the register (unusual, but say something sane).
            tableError.value = `${table.name}'s order is still being taken on another register. Ask them to send/park it, then try again.`;
            return;
        }

        await cart.resumeFromOtherTerminal(openCart.client_uuid, terminalStore.terminal.id);
        await focusSearch();
    } catch (e) {
        tableError.value = e instanceof ApiError ? e.message : 'Could not open that table.';
    }
}

// A serialized line's item is looked up from the cached catalog since
// the cart line itself only ever carries item_id -- the register never
// fetches Item afresh per line.
function needsSerial(line) {
    const item = catalog.items.find((candidate) => candidate.id === line.item_id);
    return Boolean(item?.is_serialized) && !line.serial;
}

const serialInputs = reactive({});

async function assignSerial(line) {
    const value = (serialInputs[line._tempId] || '').trim();
    if (!value) {
        return;
    }
    await cart.setLineSerial(line, value);
    delete serialInputs[line._tempId];
}

function goToPayment() {
    const missing = cart.cart.lines.find(needsSerial);
    if (missing) {
        lookupError.value = `Assign a serial number for "${missing.item_name}" before taking payment.`;
        return;
    }
    router.push({ name: 'pay' });
}

const completingQuote = ref(false);

async function completeQuote() {
    completingQuote.value = true;
    try {
        await cart.completeSale();
        if (cart.cart?.status === 'completed') {
            router.push({ name: 'confirm' });
        }
    } finally {
        completingQuote.value = false;
    }
}

const editingLineId = ref(null);
const editPrice = ref('');
const editNote = ref('');
const editBusy = ref(false);

function startEditLine(line) {
    editingLineId.value = line._tempId;
    editPrice.value = line.unit_price;
    editNote.value = line.description || '';
}

async function cancelEditLine() {
    editingLineId.value = null;
    await focusSearch();
}

async function saveLineEdit(line) {
    editBusy.value = true;
    try {
        if (editPrice.value !== '' && Number(editPrice.value) !== Number(line.unit_price)) {
            await cart.setLinePrice(line, editPrice.value);
        }
        if (editNote.value !== (line.description || '')) {
            await cart.setLineNote(line, editNote.value);
        }
    } finally {
        editBusy.value = false;
        editingLineId.value = null;
        await focusSearch();
    }
}

async function park() {
    await cart.parkCurrent();
}

async function discard() {
    await cart.finishAndReset();
}

const drawerBusy = ref(false);

async function openDrawer() {
    drawerBusy.value = true;
    try {
        await apiFetch(`/terminals/${terminalStore.terminal.id}/open-drawer`, { method: 'POST' });
        pushToast('Cash drawer opened.', 'success');
    } catch (error) {
        pushToast(error instanceof ApiError ? error.message : 'Could not open the drawer.', 'error');
    } finally {
        drawerBusy.value = false;
    }
}

const kitchenBusy = ref(false);

// incrementLine()/setLineQuantity() refuse (rather than mutate) a quantity
// increase on a line the kitchen already prepared -- catch that here and
// show it inline instead of letting it become an unhandled rejection, or
// worse, an enqueued op the sync engine's generic failure handling would
// wipe the whole cart's other queued ops over.
async function bumpLine(line) {
    try {
        await cart.incrementLine(line);
    } catch (e) {
        pushToast(e.message, 'error');
    }
}

async function adjustLineQuantity(line, quantity) {
    try {
        await cart.setLineQuantity(line, quantity);
    } catch (e) {
        pushToast(e.message, 'error');
    }
}

// A live hardware action, same reasoning as openDrawer() above -- called
// directly rather than through the offline queue, since there is nothing
// useful to do with a printed kitchen ticket after the fact if the device
// was offline when the cashier tapped this.
async function sendToKitchen() {
    kitchenBusy.value = true;
    try {
        const res = await apiFetch(`/carts/${cart.cart.id}/kitchen-ticket`, { method: 'POST' });
        cart.cart.lines.forEach((line) => { line.kitchen_sent = true; });
        // Sending to the kitchen display always succeeds independently of
        // the printer -- a print failure is a soft warning here, not a
        // failure; the order is already on the screen. No printer
        // configured at all is not a warning -- that's a normal setup.
        if (res.print_error) {
            pushToast(res.message, 'error');
        } else {
            pushToast('Order sent to the kitchen.', 'success');
        }
    } catch (error) {
        pushToast(error instanceof ApiError ? error.message : 'Could not send to the kitchen.', 'error');
    } finally {
        kitchenBusy.value = false;
    }
}
</script>

<template>
    <div class="flex h-full flex-col bg-slate-950">
        <div v-if="isOffline" class="w-full bg-amber-600/90 text-amber-50 text-center text-xs font-bold uppercase tracking-widest py-1.5 shadow-md">
            Offline — Sales Saved on This Register
        </div>
        <BarcodeCapture @scan="lookupBarcode" :beep="true" :clear-after-scan="true" />
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-800 bg-slate-900 px-3 py-2.5 sm:px-4">
            <div class="flex items-center gap-3">
                <div class="hidden h-10 w-10 shrink-0 place-items-center rounded-md bg-emerald-500/10 text-emerald-400 ring-1 ring-emerald-500/20 sm:grid">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16v11H4zM8 20h8m-6-4v4m4-4v4M8 9h8m-8 3h5" /></svg>
                </div>
                <div class="leading-tight">
                    <h1 class="text-base font-semibold text-white">{{ terminalStore.terminal?.name }}</h1>
                    <p class="max-w-48 truncate text-xs text-slate-400"><span class="hidden sm:inline">{{ terminalStore.terminal?.stock_location_name }} &middot; </span>{{ authStore.user?.name }}</p>
                </div>
                <span
                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1"
                    :class="terminalStore.terminal?.shift_open ? 'bg-emerald-500/10 text-emerald-300 ring-emerald-500/30' : 'bg-amber-500/10 text-amber-300 ring-amber-500/30'"
                >
                    <span class="h-1.5 w-1.5 rounded-full" :class="terminalStore.terminal?.shift_open ? 'bg-emerald-400' : 'bg-amber-400'"></span>
                    {{ terminalStore.terminal?.shift_open ? 'Shift open' : 'No shift' }}
                </span>
            </div>
            <div class="flex items-center gap-1.5">
                <button
                    v-if="totalParked > 0"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-700/70 bg-slate-800 px-3 py-2 text-sm font-medium text-amber-300 transition hover:bg-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400"
                    @click="showParkedDrawer = true"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Parked ({{ totalParked }})
                </button>
                <button
                    v-if="terminalStore.terminal?.has_printer && authStore.user?.can_checkout"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-700/70 bg-slate-800 px-3 py-2 text-sm font-medium text-slate-200 transition hover:bg-slate-700 hover:text-white disabled:opacity-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-500"
                    :disabled="drawerBusy"
                    @click="openDrawer"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 7h18M3 7v10a2 2 0 002 2h14a2 2 0 002-2V7M3 7l2-4h14l2 4M10 12h4" />
                    </svg>
                    <kbd class="rounded border border-slate-600/70 bg-slate-900 px-1.5 py-0.5 text-[11px] font-semibold text-slate-300">F9</kbd>
                    <span class="hidden sm:inline">{{ drawerBusy ? 'Opening…' : 'Drawer' }}</span>
                </button>
                <button
                    v-if="cart.cart && business.isRestaurantMode && cart.cart.dinner_table_id"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-700/70 bg-slate-800 px-3 py-2 text-sm font-medium text-slate-200 transition hover:bg-slate-700 hover:text-white disabled:opacity-50"
                    :disabled="kitchenBusy"
                    @click="sendToKitchen"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v10m0 0l-3-3m3 3l3-3M5 13h14l-1.5 7h-11L5 13z" />
                    </svg>
                    {{ kitchenBusy ? 'Sending…' : 'Send to kitchen' }}
                </button>
                <button
                    v-if="cart.cart"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-700/70 bg-slate-800 px-3 py-2 text-sm font-medium text-slate-200 transition hover:bg-slate-700 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-500"
                    @click="park"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 9v6M14 9v6" />
                    </svg>
                    <kbd class="rounded border border-slate-600/70 bg-slate-900 px-1.5 py-0.5 text-[11px] font-semibold text-slate-300">F2</kbd>
                    Park
                </button>
                <button
                    v-if="business.isRestaurantMode"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-700/70 bg-slate-800 px-3 py-2 text-sm font-medium text-slate-200 transition hover:bg-slate-700 hover:text-white"
                    @click="router.push({ name: 'kitchen' })"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v10m0 0l-3-3m3 3l3-3M5 13h14l-1.5 7h-11L5 13z" />
                    </svg>
                    Kitchen
                </button>
                <button
                    type="button"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-700/70 bg-slate-800 text-sm font-semibold text-slate-300 transition hover:bg-slate-700 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-500"
                    title="Keyboard shortcuts (?)"
                    aria-label="Keyboard shortcuts"
                    @click="showShortcuts = true"
                >
                    ?
                </button>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-700/70 bg-slate-800 px-3 py-2 text-sm font-medium text-slate-200 transition hover:bg-slate-700 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-500"
                    @click="signOut"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 6v1a2 2 0 01-2 2H6a2 2 0 01-2-2V7a2 2 0 012-2h5a2 2 0 012 2v1" />
                    </svg>
                    <span class="hidden sm:inline">Log out</span>
                </button>
            </div>
        </header>

        <div v-if="!cart.cart" class="flex flex-1 flex-col items-center justify-center gap-6 px-6 py-12">
            <template v-if="business.isRestaurantMode">
                <div class="flex flex-col items-center gap-4">
                    <h2 class="text-lg font-semibold text-slate-200">Select a table</h2>
                    <p v-if="tableError" class="text-sm text-red-400">{{ tableError }}</p>
                    <div v-if="tables.tables.length" class="grid w-full max-w-4xl grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                        <button
                            v-for="table in tables.tables"
                            :key="table.id"
                            type="button"
                            class="flex min-h-24 flex-col items-center justify-center gap-1 rounded-lg px-4 py-4 text-lg font-bold shadow-xl transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 sm:px-6 sm:py-5"
                            :class="table.status === 'available'
                                ? 'bg-emerald-600 text-white hover:bg-emerald-500 hover:-translate-y-1'
                                : 'bg-amber-700/60 text-amber-100 hover:bg-amber-700'"
                            @click="selectTable(table)"
                        >
                            {{ table.name }}
                            <span class="text-xs font-normal opacity-80">
                                {{ table.seats }} seats &middot; {{ table.status === 'available' ? 'Available' : 'Occupied' }}
                            </span>
                        </button>
                    </div>
                    <p v-else-if="!tables.loading" class="text-sm text-slate-500">No dinner tables configured for this location.</p>
                </div>
            </template>
            <div v-else class="flex flex-col items-center gap-5 text-center">
                <div class="flex h-16 w-16 items-center justify-center rounded-lg bg-emerald-500/10 ring-1 ring-emerald-500/30">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-xl font-semibold text-white">Ready when you are</h2>
                    <p class="mt-1 text-sm text-slate-400">Scan an item to begin, or start a sale manually.</p>
                </div>
                <div class="flex flex-wrap items-center justify-center gap-3">
                    <button
                        type="button"
                        class="inline-flex min-h-13 items-center justify-center gap-2 rounded-lg bg-emerald-500 px-8 text-lg font-bold text-slate-950 shadow-lg transition hover:bg-emerald-400 hover:-translate-y-0.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950"
                        @click="startSale"
                    >
                        Start new sale
                        <kbd class="rounded bg-slate-950/15 px-1.5 py-0.5 text-[11px] font-bold text-slate-950/80">Enter</kbd>
                    </button>
                    <button
                        v-if="business.isHardwareMode"
                        type="button"
                        class="inline-flex min-h-13 items-center justify-center rounded-lg border border-slate-700 bg-slate-900/60 px-6 text-lg font-bold text-slate-300 transition hover:bg-slate-800 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-500"
                        @click="startQuote"
                    >
                        Start quote
                    </button>
                </div>
            </div>
        </div>

        <template v-else>
            <div class="grid grid-cols-2 gap-1 border-b border-slate-800 bg-slate-900 px-3 py-2 lg:hidden" aria-label="Register view">
                <button
                    type="button"
                    class="flex h-11 items-center justify-center gap-2 rounded-md text-sm font-semibold transition"
                    :class="mobilePane === 'catalog' ? 'bg-emerald-500 text-slate-950' : 'text-slate-400 hover:bg-slate-800 hover:text-white'"
                    :aria-pressed="mobilePane === 'catalog'"
                    @click="mobilePane = 'catalog'"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16v4H4zM4 13h7v6H4zm11 0h5v6h-5z" /></svg>
                    Catalog
                </button>
                <button
                    type="button"
                    class="flex h-11 items-center justify-center gap-2 rounded-md text-sm font-semibold transition"
                    :class="mobilePane === 'cart' ? 'bg-emerald-500 text-slate-950' : 'text-slate-400 hover:bg-slate-800 hover:text-white'"
                    :aria-pressed="mobilePane === 'cart'"
                    @click="mobilePane = 'cart'"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4h2l2 11h10l2-8H6m2 12h.01M17 19h.01" /></svg>
                    Cart
                    <span v-if="cartLineCount" class="rounded bg-slate-950/20 px-1.5 py-0.5 text-[11px] font-bold">{{ cartLineCount }}</span>
                    <span v-if="cartLineCount" class="max-w-24 truncate text-xs opacity-80">{{ formatMoney(cart.cart?.totals?.total ?? roughSubtotal, cart.cart?.currency || 'USD') }}</span>
                </button>
            </div>

            <div v-if="cart.cart.lastError" class="mx-3 mt-3 flex animate-slide-down items-start justify-between gap-3 rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 sm:mx-4">
                <p class="flex items-start gap-2 text-sm font-medium text-amber-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M4.93 19h14.14c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.2 16c-.77 1.33.19 3 1.73 3z" />
                    </svg>
                    {{ cart.cart.lastError }}
                </p>
                <button type="button" class="shrink-0 rounded-lg bg-amber-500 px-3 py-1.5 text-sm font-semibold text-slate-950 transition hover:bg-amber-400" @click="discard">
                    Discard
                </button>
            </div>

            <div class="flex min-h-0 flex-1 flex-col gap-4 p-3 sm:p-4 lg:flex-row">
                <!-- Search, categories, product grid -->
                <div class="min-h-0 flex-1 flex-col lg:flex lg:w-3/5" :class="mobilePane === 'catalog' ? 'flex' : 'hidden'">
                    <div class="relative mb-3">
                        <div class="flex items-stretch gap-2">
                            <div class="relative flex-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 7V5a1 1 0 011-1h2M4 17v2a1 1 0 001 1h2m10-16h2a1 1 0 011 1v2m-3 13h2a1 1 0 001-1v-2M7 8v8m3-8v8m4-8v8m3-8v8" />
                                </svg>
                                <input
                                    ref="searchInput"
                                    v-model="search"
                                    type="text"
                                    inputmode="search"
                                    placeholder="Scan barcode or search catalog…"
                                    class="h-13 w-full rounded-xl border border-slate-700 bg-slate-900 pl-11 pr-32 text-lg text-white shadow-inner outline-none transition placeholder:text-slate-500 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/40"
                                    @keyup.enter="onSearchEnter"
                                    @keydown.down.prevent="moveHighlight(1)"
                                    @keydown.up.prevent="moveHighlight(-1)"
                                    @keydown.esc="clearSearch"
                                >
                                <span class="absolute right-2.5 top-1/2 -translate-y-1/2">
                                    <span v-if="looking" class="inline-flex items-center gap-1.5 rounded-lg bg-slate-800 px-2.5 py-1 text-xs font-medium text-emerald-300">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z" />
                                        </svg>
                                        Looking…
                                    </span>
                                    <span v-else-if="search" class="inline-flex items-center gap-1 rounded-lg bg-slate-800 px-2.5 py-1 text-xs font-medium text-slate-300">
                                        <kbd class="text-[11px] font-semibold">Enter ↵</kbd>
                                    </span>
                                    <span v-else class="inline-flex items-center gap-1.5 rounded-lg bg-slate-800 px-2.5 py-1 text-xs font-medium text-slate-400">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                                        Ready
                                    </span>
                                </span>
                            </div>
                            <button
                                type="button"
                                class="shrink-0 rounded-xl border border-slate-700 bg-slate-800 px-5 text-base font-semibold text-slate-200 transition hover:bg-slate-700 hover:text-white disabled:opacity-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-500"
                                :disabled="looking"
                                @click="runSearch()"
                            >
                                Search
                            </button>
                        </div>
                        <div v-if="searchResults.length" class="absolute z-20 mt-1.5 w-full overflow-hidden rounded-xl border border-slate-700 bg-slate-900 shadow-2xl">
                            <div class="flex items-center justify-between border-b border-slate-800 px-3.5 py-1.5 text-[11px] font-medium uppercase tracking-wide text-slate-500">
                                <span>{{ searchResults.length }} matches</span>
                                <span class="hidden sm:inline">↑↓ move · ↵ add · Esc close</span>
                            </div>
                            <button
                                v-for="(result, index) in searchResults"
                                :key="`${result.kind}-${result.id}`"
                                type="button"
                                class="flex w-full items-center justify-between gap-3 px-3.5 py-3 text-left transition"
                                :class="index === highlightedIndex ? 'bg-emerald-500/15' : 'hover:bg-slate-800'"
                                @click="pickSearchResult(result)"
                                @mousemove="highlightedIndex = index"
                            >
                                <span class="min-w-0">
                                    <span class="flex items-center gap-1.5">
                                        <span class="truncate text-sm font-medium text-white">{{ result.name }}</span>
                                        <span v-if="result.kind === 'kit'" class="shrink-0 rounded bg-amber-500/20 px-1.5 text-[10px] font-semibold uppercase text-amber-300">Kit</span>
                                    </span>
                                    <span class="mt-0.5 block truncate text-xs text-slate-400">
                                        <template v-if="result.kind === 'kit'">{{ result.kit_number }}</template>
                                        <template v-else>
                                            {{ result.sku }}
                                            <span v-if="business.isHardwareMode && result.stock_at_location !== undefined">&middot; {{ result.stock_at_location }} in stock</span>
                                        </template>
                                    </span>
                                </span>
                                <span
                                    v-if="result.kind !== 'kit' && result.has_multiple_prices"
                                    class="shrink-0 rounded bg-amber-500/20 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-amber-300"
                                >
                                    Multiple prices
                                </span>
                                <span
                                    v-else-if="result.kind !== 'kit' && result.unit_price === null"
                                    class="shrink-0 rounded bg-slate-700 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-slate-400"
                                >
                                    No price yet
                                </span>
                                <span v-else class="shrink-0 text-sm font-semibold text-emerald-300">
                                    {{ formatMoney(result.kind === 'kit' ? estimatedKitTotal(result) : result.unit_price, cart.cart.currency) }}
                                </span>
                            </button>
                        </div>
                    </div>
                    <div v-if="lookupError" class="mb-2 flex items-center gap-2 rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-2 text-sm text-red-300">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M12 3a9 9 0 100 18 9 9 0 000-18z" />
                        </svg>
                        <span>{{ lookupError }}</span>
                    </div>

                    <div class="mb-3 flex gap-2 overflow-x-auto pb-1">
                        <button
                            type="button"
                            class="shrink-0 rounded-full px-3.5 py-1.5 text-sm font-medium transition"
                            :class="activeCategoryId === null ? 'bg-emerald-500 text-slate-950' : 'bg-slate-800 text-slate-300 hover:bg-slate-700'"
                            @click="activeCategoryId = null"
                        >
                            All
                        </button>
                        <button
                            v-for="category in categories.categories"
                            :key="category.id"
                            type="button"
                            class="shrink-0 rounded-full px-3.5 py-1.5 text-sm font-medium transition"
                            :class="activeCategoryId === category.id ? 'bg-emerald-500 text-slate-950' : 'bg-slate-800 text-slate-300 hover:bg-slate-700'"
                            @click="activeCategoryId = category.id"
                        >
                            {{ category.name }}
                        </button>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto pr-1">
                        <div
                            ref="gridContainer"
                            class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 xl:grid-cols-4"
                            @keydown.down.prevent="moveGridFocus(1, 0)"
                            @keydown.up.prevent="moveGridFocus(-1, 0)"
                            @keydown.left.prevent="moveGridFocus(0, -1)"
                            @keydown.right.prevent="moveGridFocus(0, 1)"
                        >
                            <button
                                v-for="item in gridItems"
                                :key="item.id"
                                type="button"
                                class="group relative flex min-h-32 flex-col rounded-lg border border-slate-800 bg-slate-900/70 p-3.5 text-left transition hover:border-emerald-500/50 hover:bg-slate-800 focus-visible:outline-none focus-visible:border-emerald-500 focus-visible:ring-2 focus-visible:ring-emerald-500/40"
                                @click="addFromGrid(item)"
                            >
                                <span
                                    v-if="business.isHardwareMode && item.stock_at_location !== undefined"
                                    class="absolute right-2 top-2 rounded-md bg-slate-800 px-1.5 py-0.5 text-[10px] font-semibold"
                                    :class="Number(item.stock_at_location) > 0 ? 'text-slate-400' : 'text-red-400'"
                                >
                                    {{ item.stock_at_location }}
                                </span>
                                <span class="mb-2.5 flex h-10 w-10 items-center justify-center rounded-md text-sm font-bold text-white transition-transform group-hover:scale-105" :class="avatarColor(item.id)">
                                    {{ initials(item.name) }}
                                </span>
                                <span class="line-clamp-2 text-sm font-medium text-slate-100 group-hover:text-white">{{ item.name }}</span>
                                <span v-if="item.has_multiple_prices" class="mt-1.5 inline-flex w-fit rounded bg-amber-500/20 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-amber-300">
                                    Multiple prices
                                </span>
                                <span v-else-if="item.unit_price === null" class="mt-1.5 inline-flex w-fit rounded bg-slate-700 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-slate-400">
                                    No price yet
                                </span>
                                <span v-else class="mt-1.5 text-base font-semibold text-emerald-400">{{ formatMoney(item.unit_price, cart.cart.currency) }}</span>
                            </button>
                        </div>
                        <div v-if="gridItems.length === 0" class="flex flex-col items-center gap-2 py-12 text-center text-slate-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                            <p class="text-sm">No items match — scan a barcode or adjust the search.</p>
                        </div>
                    </div>
                </div>

                <!-- Cart / ticket panel -->
                <div class="min-h-0 flex-1 flex-col overflow-hidden rounded-lg border border-slate-800 bg-slate-900/70 lg:flex lg:w-2/5" :class="mobilePane === 'cart' ? 'flex' : 'hidden'">
                    <div class="flex items-center justify-between border-b border-slate-800 px-4 py-3">
                        <div class="flex items-center gap-2">
                            <h2 class="text-sm font-semibold text-white">Current sale</h2>
                            <span v-if="cartLineCount" class="rounded-full bg-slate-800 px-2 py-0.5 text-xs font-medium text-slate-300">{{ cartLineCount }}</span>
                            <span v-if="cart.cart.sale_type === 'quote'" class="rounded-full bg-sky-500/15 px-2 py-0.5 text-xs font-medium text-sky-300">Quote</span>
                        </div>
                        <button
                            v-if="cartLineCount"
                            type="button"
                            class="inline-flex items-center gap-1 text-xs font-medium text-slate-400 transition hover:text-red-300"
                            @click="discard"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2m2 0v12a2 2 0 01-2 2H8a2 2 0 01-2-2V7h12z" />
                            </svg>
                            Discard
                            <kbd class="rounded border border-slate-700 bg-slate-800 px-1 text-[10px] font-semibold text-slate-400">Esc</kbd>
                        </button>
                    </div>

                    <div class="flex min-h-0 flex-1 flex-col p-4">
                      <div class="space-y-2.5">
                        <div v-if="cart.cart.dinner_table" class="rounded-xl border border-slate-800 bg-slate-800/60 px-3.5 py-2.5 text-sm font-medium text-white">
                            Table: <span class="text-emerald-300">{{ cart.cart.dinner_table.name }}</span>
                        </div>

                        <div v-if="cart.cart.customer" class="flex items-center justify-between rounded-xl border border-slate-800 bg-slate-800/60 px-3.5 py-2.5">
                            <span class="min-w-0 truncate text-sm text-white">
                                <span class="text-slate-400">Customer</span> &middot; {{ cart.cart.customer.company_name || cart.cart.customer.full_name }}
                            </span>
                            <button type="button" class="shrink-0 text-xs font-medium text-red-400 transition hover:text-red-300" @click="removeCustomer">Remove</button>
                        </div>
                        <div v-else class="relative">
                            <input
                                ref="customerQueryInput"
                                v-model="customerQuery"
                                type="text"
                                placeholder="Attach customer — name or phone  (F6)"
                                class="h-10 w-full rounded-xl border border-slate-700 bg-slate-950 px-3.5 text-sm text-white shadow-inner outline-none transition placeholder:text-slate-500 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/40"
                                @input="onCustomerQueryInput"
                            >
                            <span v-if="customerSearching" class="absolute right-3 top-1/2 -translate-y-1/2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 animate-spin text-slate-500" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z" />
                                </svg>
                            </span>
                            <div v-if="customerResults.length" class="absolute z-20 mt-1 w-full space-y-1 rounded-xl border border-slate-700 bg-slate-900 p-2 shadow-2xl">
                                <button
                                    v-for="result in customerResults"
                                    :key="result.id"
                                    type="button"
                                    class="block w-full rounded-lg px-3 py-2 text-left text-sm text-white transition hover:bg-slate-800"
                                    @click="attachCustomer(result)"
                                >
                                    <span class="font-medium">{{ result.company_name || result.full_name }}</span>
                                    <span class="mt-0.5 block text-xs text-slate-400">{{ result.full_name }} &middot; {{ result.email || result.phone }}</span>
                                </button>
                            </div>
                        </div>

                        <div v-if="cart.cart.coupon_code" class="flex items-center justify-between rounded-xl border border-emerald-500/25 bg-emerald-500/10 px-3.5 py-2.5">
                            <span class="text-sm text-white"><span class="text-slate-400">Coupon</span> &middot; <span class="font-medium text-emerald-300">{{ cart.cart.coupon_code }}</span></span>
                            <button type="button" class="text-xs font-medium text-red-400 transition hover:text-red-300 disabled:opacity-50" :disabled="couponBusy" @click="removeCoupon">Remove</button>
                        </div>
                        <template v-else>
                            <button v-if="!showCouponField" type="button" class="self-start text-xs font-medium text-slate-400 transition hover:text-white" @click="showCouponField = true">
                                + Add coupon
                            </button>
                            <div v-else class="flex gap-2">
                                <input
                                    v-model="couponCode"
                                    type="text"
                                    placeholder="Coupon code"
                                    class="h-10 flex-1 rounded-xl border border-slate-700 bg-slate-950 px-3.5 text-sm text-white shadow-inner outline-none transition placeholder:text-slate-500 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/40"
                                    @keyup.enter="applyCoupon"
                                >
                                <button
                                    type="button"
                                    class="rounded-xl border border-slate-700 bg-slate-800 px-4 text-sm font-semibold text-slate-200 transition hover:bg-slate-700 hover:text-white disabled:opacity-50"
                                    :disabled="couponBusy"
                                    @click="applyCoupon"
                                >
                                    Apply
                                </button>
                            </div>
                        </template>
                      </div>

                        <div class="mt-3 min-h-0 flex-1 space-y-2 overflow-y-auto pr-1">
                            <div
                                v-for="line in cart.cart.lines"
                                :key="line._tempId"
                                class="animate-slide-down rounded-xl border border-slate-800 bg-slate-800/50 p-3"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-1.5">
                                            <span class="truncate text-sm font-medium text-white">{{ line.item_name }}</span>
                                            <span v-if="line.kitchen_sent" class="shrink-0 rounded bg-emerald-500/20 px-1 text-[10px] font-semibold uppercase text-emerald-300">Sent</span>
                                        </div>
                                        <div class="mt-0.5 text-xs text-slate-400">
                                            {{ formatMoney(line.unit_price, cart.cart.currency) }} each
                                            <span v-if="line.price_overridden" class="text-amber-400">&middot; overridden</span>
                                            <span v-if="business.isHardwareMode && line.stock_at_location !== undefined">&middot; {{ line.stock_at_location }} in stock</span>
                                        </div>
                                        <div v-if="line.description" class="mt-0.5 text-xs text-slate-500">{{ line.description }}</div>
                                        <div v-if="line.serial" class="mt-0.5 text-xs text-slate-500">Serial: {{ line.serial }}</div>
                                    </div>
                                    <div class="shrink-0 text-right text-sm font-semibold text-white tabular-nums">
                                        {{ formatMoney(lineTotal(line), cart.cart.currency) }}
                                    </div>
                                </div>

                                <div v-if="needsSerial(line)" class="mt-2 flex items-center gap-2">
                                    <input
                                        v-model="serialInputs[line._tempId]"
                                        type="text"
                                        placeholder="Scan or type serial"
                                        class="h-9 flex-1 rounded-lg border border-amber-500/50 bg-slate-900 px-2.5 text-sm text-white outline-none focus:ring-2 focus:ring-amber-500/50"
                                        @keydown.enter="assignSerial(line)"
                                    >
                                    <button
                                        type="button"
                                        class="h-9 rounded-lg bg-amber-500 px-3 text-sm font-semibold text-slate-950 transition hover:bg-amber-400"
                                        @click="assignSerial(line)"
                                    >
                                        Assign
                                    </button>
                                </div>

                                <div class="mt-2.5 flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-1 rounded-xl border border-slate-700 bg-slate-900/60 p-1">
                                        <button
                                            type="button"
                                            aria-label="Decrease quantity"
                                            class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-800 text-xl font-medium text-white transition hover:bg-slate-700 active:scale-95"
                                            @click="cart.decrementLine(line)"
                                        >
                                            −
                                        </button>
                                        <input
                                            type="number"
                                            min="0"
                                            :step="business.isHardwareMode ? '0.001' : '1'"
                                            :value="line.quantity"
                                            class="h-10 w-16 rounded-lg bg-transparent text-center text-base font-semibold text-white outline-none focus:bg-slate-800 focus:ring-2 focus:ring-emerald-500/50"
                                            @change="adjustLineQuantity(line, $event.target.value)"
                                        >
                                        <button
                                            type="button"
                                            aria-label="Increase quantity"
                                            class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-800 text-xl font-medium text-white transition hover:bg-slate-700 active:scale-95"
                                            @click="bumpLine(line)"
                                        >
                                            +
                                        </button>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <button
                                            v-if="business.isHardwareMode && editingLineId !== line._tempId"
                                            type="button"
                                            aria-label="Edit line"
                                            class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-700 hover:text-white"
                                            @click="startEditLine(line)"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M4 20h4L18.5 9.5a2.5 2.5 0 00-3.536-3.536L4 16v4z" />
                                            </svg>
                                        </button>
                                        <button
                                            type="button"
                                            aria-label="Remove line"
                                            class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-400 transition hover:bg-red-500/15 hover:text-red-300"
                                            @click="cart.removeLine(line)"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2m2 0v12a2 2 0 01-2 2H8a2 2 0 01-2-2V7h12z" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <div v-if="editingLineId === line._tempId" class="mt-3 space-y-2.5 border-t border-slate-700 pt-3">
                                    <div class="flex flex-wrap items-end gap-3">
                                        <label class="block">
                                            <span class="mb-1 block text-[11px] font-medium uppercase tracking-wide text-slate-400">Unit price</span>
                                            <input
                                                v-model="editPrice"
                                                type="text"
                                                inputmode="decimal"
                                                class="h-9 w-28 rounded-lg bg-slate-900 px-2.5 text-white outline-none focus:ring-2 focus:ring-emerald-500/50"
                                                @keydown.esc="cancelEditLine"
                                            >
                                        </label>
                                        <label class="block flex-1">
                                            <span class="mb-1 block text-[11px] font-medium uppercase tracking-wide text-slate-400">Note (cut size, delivery, custom request)</span>
                                            <input
                                                v-model="editNote"
                                                type="text"
                                                class="h-9 w-full rounded-lg bg-slate-900 px-2.5 text-white outline-none focus:ring-2 focus:ring-emerald-500/50"
                                                @keydown.esc="cancelEditLine"
                                            >
                                        </label>
                                    </div>
                                    <div class="flex gap-2">
                                        <button
                                            type="button"
                                            class="rounded-lg bg-emerald-500 px-3.5 py-1.5 text-sm font-semibold text-slate-950 transition hover:bg-emerald-400 disabled:opacity-50"
                                            :disabled="editBusy"
                                            @click="saveLineEdit(line)"
                                        >
                                            {{ editBusy ? 'Saving…' : 'Save' }}
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-lg border border-slate-600 px-3.5 py-1.5 text-sm text-slate-300 transition hover:bg-slate-800"
                                            @click="cancelEditLine"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div v-if="cartLineCount === 0" class="flex flex-col items-center gap-2 rounded-xl border border-dashed border-slate-800 py-10 text-center text-slate-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 7V5a1 1 0 011-1h2M4 17v2a1 1 0 001 1h2m10-16h2a1 1 0 011 1v2m-3 13h2a1 1 0 001-1v-2M7 8v8m3-8v8m4-8v8m3-8v8" />
                                </svg>
                                <p class="text-sm">Scan a barcode or tap a product to add it.</p>
                            </div>
                        </div>
                    </div>

                    <footer class="border-t border-slate-800 bg-slate-900 px-4 py-3.5">
                        <div class="space-y-1 text-sm">
                            <div class="flex justify-between text-slate-400">
                                <span>Subtotal</span>
                                <span class="tabular-nums text-slate-200">{{ formatMoney(cart.cart.totals?.subtotal ?? 0, cart.cart.currency) }}</span>
                            </div>
                            <div v-if="Number(cart.cart.totals?.discount_total) > 0" class="flex justify-between text-slate-400">
                                <span>Discount</span>
                                <span class="tabular-nums text-emerald-300">−{{ formatMoney(cart.cart.totals.discount_total, cart.cart.currency) }}</span>
                            </div>
                            <div v-if="Number(cart.cart.totals?.tax_total) > 0" class="flex justify-between text-slate-400">
                                <span>Tax</span>
                                <span class="tabular-nums text-slate-200">{{ formatMoney(cart.cart.totals.tax_total, cart.cart.currency) }}</span>
                            </div>
                        </div>
                        <div class="mt-2.5 flex items-end justify-between border-t border-slate-800 pt-2.5">
                            <span class="flex items-center gap-2 text-sm font-medium text-slate-400">
                                Total
                                <span v-if="cart.hasUnsyncedChanges" class="inline-flex items-center gap-1 rounded-full bg-slate-800 px-2 py-0.5 text-[11px] text-amber-300">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z" />
                                    </svg>
                                    estimating
                                </span>
                            </span>
                            <span class="text-3xl font-bold tracking-tight text-emerald-400 tabular-nums">
                                {{ formatMoney(cart.hasUnsyncedChanges ? roughSubtotal : (cart.cart.totals?.total ?? 0), cart.cart.currency) }}
                            </span>
                        </div>
                        <button
                            v-if="cart.cart.sale_type === 'quote'"
                            type="button"
                            :disabled="cartLineCount === 0 || completingQuote"
                            class="mt-3 flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-500 py-3.5 text-lg font-bold text-slate-950 shadow-lg transition hover:bg-emerald-400 hover:-translate-y-0.5 disabled:opacity-40 disabled:pointer-events-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950"
                            @click="completeQuote"
                        >
                            <kbd class="rounded bg-slate-950/15 px-1.5 py-0.5 text-[11px] font-bold text-slate-950/80">F4</kbd>
                            {{ completingQuote ? 'Saving…' : 'Save quote' }}
                        </button>
                        <button
                            v-else-if="authStore.user?.can_checkout"
                            type="button"
                            :disabled="cartLineCount === 0"
                            class="mt-3 flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-500 py-3.5 text-lg font-bold text-slate-950 shadow-lg transition hover:bg-emerald-400 hover:-translate-y-0.5 disabled:opacity-40 disabled:pointer-events-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950"
                            @click="goToPayment"
                        >
                            <kbd class="rounded bg-slate-950/15 px-1.5 py-0.5 text-[11px] font-bold text-slate-950/80">F4</kbd>
                            Take payment
                        </button>
                        <button
                            v-else
                            type="button"
                            :disabled="cartLineCount === 0"
                            class="mt-3 flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-500 py-3.5 text-lg font-bold text-slate-950 shadow-lg transition hover:bg-emerald-400 hover:-translate-y-0.5 disabled:opacity-40 disabled:pointer-events-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950"
                            @click="park"
                        >
                            <kbd class="rounded bg-slate-950/15 px-1.5 py-0.5 text-[11px] font-bold text-slate-950/80">F4</kbd>
                            Send order to cashier
                        </button>
                    </footer>
                </div>
            </div>
        </template>
    </div>

    <!-- Parked Sales Drawer Overlay -->
    <div v-if="showParkedDrawer" class="fixed inset-0 z-50 flex justify-end">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm transition-opacity" @click="showParkedDrawer = false"></div>
        
        <div class="relative flex w-full max-w-md flex-col bg-slate-900 shadow-2xl h-full border-l border-slate-800 animate-slide-left">
            <div class="flex items-center justify-between border-b border-slate-800 px-6 py-5">
                <h2 class="text-xl font-bold text-white">Parked Sales</h2>
                <button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-slate-800 hover:text-white transition-colors" @click="showParkedDrawer = false">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            
            <div class="flex-1 overflow-y-auto p-6 space-y-8">
                <div v-if="totalParked === 0" class="text-center text-slate-500 mt-10">
                    No parked sales right now.
                </div>
                
                <div v-if="cart.parked.length" class="space-y-3">
                    <div class="flex items-center gap-4 before:h-px before:flex-1 before:bg-slate-800 after:h-px after:flex-1 after:bg-slate-800">
                        <p class="text-sm font-semibold uppercase tracking-wider text-slate-500">This Terminal</p>
                    </div>
                    <div v-for="parkedCart in cart.parked" :key="parkedCart.client_uuid" class="flex items-stretch gap-2 group">
                        <button
                            type="button"
                            class="flex-1 rounded-xl bg-slate-800/80 border border-slate-700/50 px-5 py-4 text-left text-white shadow-sm hover:bg-slate-700 hover:border-slate-500 transition-all"
                            @click="cart.resume(parkedCart.client_uuid); showParkedDrawer = false;"
                        >
                            <span class="font-medium">{{ parkedCart.lines.length }} item(s)</span>
                            <span class="mx-2 text-slate-500">&middot;</span>
                            <span class="font-bold text-emerald-400">{{ formatMoney(parkedCart.totals.total, parkedCart.currency) }}</span>
                        </button>
                        <button
                            type="button"
                            class="flex shrink-0 items-center justify-center rounded-xl bg-slate-900/50 border border-transparent px-4 text-slate-500 hover:border-red-900 hover:bg-red-950 hover:text-red-400 transition-all opacity-50 group-hover:opacity-100"
                            title="Delete parked sale"
                            @click="deleteParkedCart(parkedCart)"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2m2 0v12a2 2 0 01-2 2H8a2 2 0 01-2-2V7h12z" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div v-if="otherTerminalCarts.length" class="space-y-3">
                    <div class="flex items-center gap-4 before:h-px before:flex-1 before:bg-slate-800 after:h-px after:flex-1 after:bg-slate-800">
                        <p class="text-sm font-semibold uppercase tracking-wider text-slate-500">Other Terminals</p>
                    </div>
                    <div v-for="otherCart in otherTerminalCarts" :key="otherCart.client_uuid" class="flex items-stretch gap-2 group">
                        <button
                            type="button"
                            class="flex-1 rounded-xl bg-slate-800/80 border border-slate-700/50 px-5 py-4 text-left text-white shadow-sm hover:bg-slate-700 hover:border-slate-500 transition-all"
                            @click="resumeOtherTerminalCart(otherCart); showParkedDrawer = false;"
                        >
                            <span class="font-medium">{{ otherCart.lines.length }} item(s)</span>
                            <span class="mx-2 text-slate-500">&middot;</span>
                            <span class="font-bold text-emerald-400">{{ formatMoney(otherCart.totals.total, otherCart.currency) }}</span>
                            <span v-if="otherCart.customer" class="block mt-1 text-sm font-medium text-slate-400">{{ otherCart.customer.company_name || otherCart.customer.full_name }}</span>
                        </button>
                        <button
                            type="button"
                            class="flex shrink-0 items-center justify-center rounded-xl bg-slate-900/50 border border-transparent px-4 text-slate-500 hover:border-red-900 hover:bg-red-950 hover:text-red-400 transition-all opacity-50 group-hover:opacity-100"
                            title="Delete parked sale"
                            @click="deleteOtherTerminalCart(otherCart)"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2m2 0v12a2 2 0 01-2 2H8a2 2 0 01-2-2V7h12z" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toasts -->
    <div class="pointer-events-none fixed right-4 top-4 z-60 flex w-80 max-w-[calc(100vw-2rem)] flex-col gap-2">
        <div
            v-for="toast in toasts"
            :key="toast.id"
            class="animate-slide-down pointer-events-auto flex items-start gap-2.5 rounded-xl border px-3.5 py-2.5 text-sm shadow-2xl backdrop-blur"
            :class="{
                'border-emerald-500/30 bg-emerald-500/15 text-emerald-100': toast.type === 'success',
                'border-red-500/30 bg-red-500/15 text-red-100': toast.type === 'error',
                'border-slate-700 bg-slate-800/95 text-slate-100': toast.type === 'info',
            }"
        >
            <span class="flex-1">{{ toast.message }}</span>
            <button type="button" class="shrink-0 text-slate-400 transition hover:text-white" aria-label="Dismiss" @click="dismissToast(toast.id)">✕</button>
        </div>
    </div>

    <!-- Keyboard shortcuts -->
    <div v-if="showShortcuts" class="fixed inset-0 z-70 flex items-center justify-center p-4" @click.self="showShortcuts = false">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showShortcuts = false"></div>
        <div class="relative w-full max-w-sm rounded-2xl border border-slate-800 bg-slate-900 p-5 shadow-2xl">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-white">Keyboard shortcuts</h2>
                <button type="button" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-800 hover:text-white" aria-label="Close" @click="showShortcuts = false">✕</button>
            </div>
            <dl class="space-y-2 text-sm">
                <div v-for="row in [
                        { k: 'Enter', label: 'Start sale / add top match' },
                        { k: 'F2', label: 'Park sale' },
                        { k: 'F4', label: 'Take payment' },
                        { k: 'F6', label: 'Attach customer' },
                        { k: 'F9', label: 'Open cash drawer' },
                        { k: '+ / −', label: 'Adjust last line quantity' },
                        { k: 'Esc', label: 'Clear search / discard sale' },
                        { k: '?', label: 'Show this help' },
                    ]" :key="row.k" class="flex items-center justify-between gap-4">
                    <dt class="text-slate-300">{{ row.label }}</dt>
                    <dd><kbd class="rounded border border-slate-700 bg-slate-800 px-1.5 py-0.5 text-[11px] font-semibold text-slate-300">{{ row.k }}</kbd></dd>
                </div>
            </dl>
        </div>
    </div>

    <Teleport to="body">
        <div v-if="priceOptions.length" class="fixed inset-0 z-[100] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="price-picker-title">
            <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="cancelPricePicker"></div>
            <div class="relative flex max-h-[calc(100dvh-2rem)] w-full max-w-md flex-col overflow-hidden rounded-lg border border-slate-700 bg-slate-900 shadow-2xl">
                <div class="flex items-start justify-between gap-4 border-b border-slate-800 px-5 py-4">
                    <div>
                        <h2 id="price-picker-title" class="text-lg font-bold text-white">Choose a price</h2>
                        <p class="mt-1 text-sm text-slate-400">{{ pendingPriceItem?.name }}</p>
                    </div>
                    <button type="button" class="grid h-10 w-10 shrink-0 place-items-center rounded-md text-slate-400 transition hover:bg-slate-800 hover:text-white disabled:opacity-40" aria-label="Close price picker" :disabled="pricePickerBusy" @click="cancelPricePicker">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" /></svg>
                    </button>
                </div>

                <div class="min-h-0 overflow-y-auto p-4 sm:p-5">
                    <p class="mb-3 text-sm text-slate-300">Select the lot shown on the unit. The selling price and stock batch will be recorded together.</p>
                    <div v-if="pricePickerError" role="alert" class="mb-3 rounded-md border border-red-500/30 bg-red-500/10 px-3 py-2.5 text-sm text-red-200">
                        {{ pricePickerError }}
                    </div>
                    <div class="space-y-2">
                        <button
                            v-for="option in priceOptions"
                            :key="option.stock_lot_id"
                            type="button"
                            class="flex min-h-16 w-full items-center justify-between gap-3 rounded-lg border border-slate-700 bg-slate-800/60 px-4 py-3 text-left transition hover:border-emerald-500/60 hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 disabled:cursor-wait disabled:opacity-60"
                            :disabled="pricePickerBusy"
                            @click="choosePriceOption(option)"
                        >
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-semibold text-white">Lot {{ option.lot_number }}</span>
                                <span class="mt-0.5 block text-xs text-slate-400">{{ option.expires_on ? `Expires ${option.expires_on}` : 'No expiry date' }}</span>
                            </span>
                            <span class="flex shrink-0 items-center gap-2">
                                <svg v-if="pricePickerBusy && selectedPriceLotId === option.stock_lot_id" class="h-4 w-4 animate-spin text-emerald-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z" /></svg>
                                <span class="text-lg font-bold text-emerald-300">{{ formatMoney(option.price, cart.cart?.currency || 'USD') }}</span>
                                <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
