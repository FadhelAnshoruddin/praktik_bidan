import defaultProfilePhoto from '../aset/8c89575a-e61f-4b88-99f5-c519abded0fa.jpg';

document.addEventListener('click', async (event) => {
  const menuButton = event.target.closest('[data-menu-toggle]');
  if (menuButton) {
    document.querySelector('.public-nav')?.classList.toggle('is-open');
  }

  const copyButton = event.target.closest('[data-copy]');
  if (copyButton) {
    try {
      await navigator.clipboard.writeText(copyButton.dataset.copy);
      copyButton.textContent = 'Tersalin';
    } catch {
      copyButton.textContent = copyButton.dataset.copy;
    }
  }
});

document.querySelectorAll('[data-default-photo]').forEach((image) => {
  if (!image.getAttribute('src')) image.src = defaultProfilePhoto;
});

const bandungClock = new Intl.DateTimeFormat('id-ID', {
  timeZone: 'Asia/Jakarta',
  weekday: 'long',
  day: 'numeric',
  month: 'long',
  year: 'numeric',
  hour: '2-digit',
  minute: '2-digit',
  hourCycle: 'h23',
});
const bandungDate = new Intl.DateTimeFormat('en-CA', {
  timeZone: 'Asia/Jakarta',
  year: 'numeric',
  month: '2-digit',
  day: '2-digit',
});
let lastBandungDate = '';
let refreshingTodaySlots = false;
let lastSlotRefresh = 0;

function getBandungDate(date) {
  const parts = Object.fromEntries(bandungDate.formatToParts(date).map(({ type, value }) => [type, value]));
  return `${parts.year}-${parts.month}-${parts.day}`;
}

function updateAppointmentSlots(form, slots, preferredTime = '') {
  const timeSelect = form.querySelector('[data-registration-time-select]');
  if (!timeSelect) return 0;

  timeSelect.replaceChildren(new Option('Pilih jam pemeriksaan', ''));
  slots.forEach((slot) => {
    const label = `${slot.time} WIB${slot.reason ? ` · ${slot.reason}` : ''}`;
    const option = new Option(label, slot.time);
    option.disabled = !slot.available;
    timeSelect.add(option);
  });
  const available = slots.filter((slot) => slot.available).map((slot) => slot.time);
  timeSelect.value = available.includes(preferredTime) ? preferredTime : (available[0] ?? '');
  timeSelect.disabled = available.length === 0;

  return available.length;
}

async function refreshTodaySlots() {
  const form = document.querySelector('[data-registration-form]');
  const timeSelect = form?.querySelector('[data-registration-time-select]');
  if (!form || !timeSelect || refreshingTodaySlots) return;

  refreshingTodaySlots = true;
  try {
    const preferredTime = timeSelect.value;
    const response = await fetch(form.dataset.todaySlotsUrl, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    });
    if (!response.ok) return;

    const booking = await response.json();
    const dateInput = form.querySelector('[data-registration-date]');
    const dateLabel = form.querySelector('[data-today-date-label]');
    if (dateInput) dateInput.value = booking.date;
    if (dateLabel) dateLabel.textContent = booking.dateLabel;
    const availableCount = updateAppointmentSlots(form, booking.slots, preferredTime);
    const notice = form.querySelector('[data-registration-no-slots]');
    const submit = form.querySelector('[data-registration-submit]');
    if (notice) {
      notice.hidden = booking.isOpen && booking.isScheduled && availableCount > 0;
      const message = !booking.isOpen
        ? ' Pendaftaran sedang ditutup sementara.'
        : !booking.isScheduled
          ? ' Praktik tidak buka hari ini.'
          : ' Semua jam pemeriksaan hari ini sudah lewat atau terisi.';
      notice.lastChild.textContent = message;
    }
    if (submit) submit.disabled = !booking.isOpen || !booking.isScheduled || availableCount === 0;
    lastSlotRefresh = Date.now();
  } catch {
    return;
  } finally {
    refreshingTodaySlots = false;
  }
}

function updateBandungClock() {
  const now = new Date();
  document.querySelectorAll('[data-bandung-clock]').forEach((element) => {
    element.textContent = bandungClock.format(now);
  });
  const today = getBandungDate(now);
  if (today !== lastBandungDate || Date.now() - lastSlotRefresh >= 30000) refreshTodaySlots();
  lastBandungDate = today;
}

updateBandungClock();
window.setInterval(updateBandungClock, 30000);

document.addEventListener('change', (event) => {
  const scheduleToggle = event.target.closest('.schedule-check input[type="checkbox"]');
  if (!scheduleToggle) return;

  scheduleToggle.closest('.schedule-line')
    ?.querySelectorAll('input[type="time"]')
    .forEach((input) => { input.disabled = !scheduleToggle.checked; });
});