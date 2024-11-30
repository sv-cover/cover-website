<?php

namespace App\Utils\WebCal;

class Calendar implements WebCalInterface
{
    use WebCalTrait;

    public array $events = [];

    public array $additional_headers = [];

    public function __construct(
        public string $name,
        public string $description,
    ) {
    }
    
    public function add(Event $event): void
    {
        $this->events[] = $event;
    }

    public function getHeaders(?string $filename = null): array
    {
        $headers = [
            'Content-Type' => 'text/calendar; charset=UTF-8',
        ];

        if ($filename)
            $headers['Content-Disposition'] = 'attachment; filename="' . $filename . '"';

        return $headers;
    }
    
    protected function exportWebCalHeaders(): array
    {
        $out = [
            'METHOD:PUBLISH',
            'CALSCALE:GREGORIAN',
            'VERSION:2.0',
            'PRODID:-//svCover.nl//NONSGML Cover Calendar v1.0//EN',
            'X-WR-CALNAME:' . $this->encode($this->name),
            'X-WR-CALDESC:' . $this->encode($this->description),
            'X-WR-RELCALID:' . \md5($this->name),
        ];

        return \array_merge($out, $this->additional_headers);
    }

    public function export(): string
    {
        $out = array_merge(
            ['BEGIN:VCALENDAR'],
            $this->exportWebCalHeaders(),
        );
        
        foreach ($this->events as $event)
            $out[] = $event->export();
        
        $out[] = 'END:VCALENDAR';
        
        return implode("\r\n", $out);
    }

    public function inject(string $data): void
    {
        \preg_match_all('/(*ANYCRLF)^BEGIN:VTIMEZONE$.*?^END:VTIMEZONE$/sm', $data, $timezones);
        $this->additional_headers = \array_merge(
            $this->additional_headers,
            $timezones[0]
        );

        \preg_match_all('/(*ANYCRLF)^BEGIN:VEVENT$.*?^END:VEVENT$/sm', $data, $events);
        foreach ($events[0] as $event)
            $this->add(new ExternalEvent($event));
    }
}
