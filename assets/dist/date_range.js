function countWholeMonths(start, end) {
    const startsOnFirstDay = start.day === 1;
    const endsOnLastDay = end.day === end.daysInMonth;
    if (!startsOnFirstDay || !endsOnLastDay) {
        return 0;
    }
    return (end.year - start.year) * 12 + end.month - start.month + 1;
}
export function shiftRange([start, end], direction) {
    const months = countWholeMonths(start, end);
    if (months > 0) {
        const shiftedStart = start.plus({ months: months * direction });
        return [shiftedStart, shiftedStart.plus({ months }).minus({ days: 1 })];
    }
    const days = Math.round(end.startOf('day').diff(start.startOf('day'), 'days').days) + 1;
    const offset = { days: days * direction };
    return [start.plus(offset), end.plus(offset)];
}
