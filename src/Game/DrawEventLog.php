<?php

declare(strict_types=1);

namespace Chess\Game;

/** Draw negotiation history for a match, sliced the same way as plies for polls. */
final class DrawEventLog
{
    /** @param list<RecordedDrawEvent> $events */
    public function __construct(private readonly array $events)
    {
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public function count(): int
    {
        return count($this->events);
    }

    /**
     * @return list<RecordedDrawEvent>
     */
    public function since(int $cursor): array
    {
        return array_values(array_filter(
            $this->events,
            static fn (RecordedDrawEvent $event): bool => $event->number > $cursor,
        ));
    }
}
