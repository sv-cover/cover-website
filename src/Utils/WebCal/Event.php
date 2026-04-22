<?php

namespace App\Utils\WebCal;

class Event implements WebCalInterface
{
    use WebCalTrait;

    public function __construct(
        public \DateTime|string $start,
        public \DateTime|string|null $end = null,
        public ?string $uid = null,
        public ?string $summary = null,
        public ?string $description = null,
        public ?string $location = null,
        public ?string $url = null,
    ) {
        if (\is_string($start))
            $this->start = new \DateTime($start);

        if ($end && \is_string($end))
            $this->end = new \DateTime($end);
    }

    public function export(): string
    {
        $start = $this->start;
        $end = $this->end;

        $descriptionHeaders = [];

        $out = ['BEGIN:VEVENT'];

        if ($start->format('Hi') == '0000' && (!$end || $end->format('Hi') == '0000')) {
            // Is this a all-day event? If so, only add the date.

            if (!$end)
                // Is there an end date? Yes? Good! Otherwise, make the event one day long.
                $end = \DateTime::createFromInterface($start)->modify('+1 day');
            
            $out[] = 'DTSTART;VALUE=DATE:' . $start->format('Ymd');
            $out[] = 'DTEND;VALUE=DATE:' . $end->format('Ymd');
        } elseif ($end && $end->diff($start)->days >= 1) {
            // Maybe it's a multiday event?
            $out[] = 'DTSTART;VALUE=DATE:' . $start->format('Ymd');
            $out[] = 'DTEND;VALUE=DATE:' . $end->format('Ymd');

            // Use array_merge to get these descriptionHeaders to the top
            $descriptionHeaders = \array_merge([
                'Start: ' . $start->format('Y-m-d H:i'),
                'End: ' . $end->format('Y-m-d H:i'),
            ], $descriptionHeaders);
        } else {
            // No it is not, just add date and time.
            $start->setTimezone(new \DateTimeZone('GMT'));
            // Is there an end time? No? Let the event be till the end of the day.
            if (!$end)
                $end = \DateTime::createFromInterface($start)->modify('00:00:00')->modify('+1 day');
            else
                $end->setTimezone(new \DateTimeZone('GMT'));

            $out[] = 'DTSTART:' . $start->format('Ymd\THis\Z');
            $out[] = 'DTEND:' . $end->format('Ymd\THis\Z');
        }

        // Add some optional fields to the calendar item
        if ($this->uid)
            $out[] = 'UID:' . $this->encode($this->uid);

        if ($this->summary)
            $out[] = 'SUMMARY:' . $this->encode($this->summary);

        if ($this->location)
            $out[] = 'LOCATION:' . $this->encode($this->location);

        if ($this->url)
            $descriptionHeaders[] = 'More information: ' . $this->url;

        if ($this->description && !empty($descriptionHeaders))
            $this->description = \sprintf("%s\n\n%s", \implode("\n", $descriptionHeaders), $this->description);
        elseif (!empty($descriptionHeaders))
            $this->description = \implode("\n", $descriptionHeaders);

        $out[] = 'DESCRIPTION:' . $this->encode($this->description ?? '');
        $out[] = 'END:VEVENT';
        
        return \implode("\r\n", $out);
    }
}
