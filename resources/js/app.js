// Flatpickr
import flatpickr from "flatpickr"
import "flatpickr/dist/flatpickr.min.css";

import noUiSlider from 'nouislider';
import '../css/nouislider.css';
window.noUiSlider = noUiSlider;

// Alpine Calendar (date range filter). Livewire owns Alpine, so register the
// plugin on its instance: right away when this script runs after Livewire's
// (Alpine is defined but not started yet), otherwise as Alpine boots.
import { calendarPlugin } from '@reachweb/alpine-calendar';
import '@reachweb/alpine-calendar/css';

let calendarRegistered = false;
const registerCalendar = () => {
    if (calendarRegistered || ! window.Alpine) return;
    window.Alpine.plugin(calendarPlugin);
    calendarRegistered = true;
};
registerCalendar();
document.addEventListener('alpine:init', registerCalendar);
