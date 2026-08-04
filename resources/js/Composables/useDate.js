import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
import 'dayjs/locale/es';

dayjs.extend(relativeTime);
dayjs.locale('es');

export function formatDate(value, format = 'DD/MM/YYYY HH:mm') {
    if (!value) return '—';
    return dayjs(value).format(format);
}

export function timeAgo(value) {
    if (!value) return '';
    return dayjs(value).fromNow();
}

export default dayjs;
