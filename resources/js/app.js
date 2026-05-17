import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

window.ioeCountdown = function (targetIso) {
    return {
        days: '00',
        hours: '00',
        minutes: '00',
        seconds: '00',
        timer: null,
        init() {
            this.tick();
            this.timer = setInterval(() => this.tick(), 1000);
        },
        tick() {
            const target = new Date(targetIso).getTime();
            const diff = Math.max(Math.floor((target - Date.now()) / 1000), 0);
            this.days = String(Math.floor(diff / 86400)).padStart(2, '0');
            this.hours = String(Math.floor((diff % 86400) / 3600)).padStart(2, '0');
            this.minutes = String(Math.floor((diff % 3600) / 60)).padStart(2, '0');
            this.seconds = String(diff % 60).padStart(2, '0');
        },
    };
};

Alpine.start();
