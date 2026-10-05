<?php

declare(strict_types=1);

namespace Chess\Game;

use DateTimeImmutable;
use PDO;
use Throwable;

final class MatchRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function insert(GameMatch $match): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO matches
                (id, status, fen, time_control_seconds, creator_color, creator_token_hash, created_at)
             VALUES (:id, :status, :fen, :time_control_seconds, :creator_color, :creator_token_hash, :created_at)',
        );

        $statement->execute([
            'id' => $match->id,
            'status' => $match->status->value,
            'fen' => $match->fen,
            'time_control_seconds' => $match->timeControl->value,
            'creator_color' => $match->creatorColor->value,
            'creator_token_hash' => $match->creatorTokenHash,
            'created_at' => $match->createdAt->format('Y-m-d H:i:s.v'),
        ]);
    }

    /**
     * Persists a match whose joiner seat has just been filled, or reports false
     * if someone else got there first. The preconditions live in the WHERE so
     * two simultaneous visitors cannot both be seated: only one UPDATE can
     * match a row whose seat is still empty.
     */
    public function claimJoiner(GameMatch $seated): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE matches
                SET joiner_token_hash = :joiner_token_hash, status = :status
              WHERE id = :id AND joiner_token_hash IS NULL AND status = :waiting',
        );

        $statement->execute([
            'joiner_token_hash' => $seated->joinerTokenHash,
            'status' => $seated->status->value,
            'id' => $seated->id,
            'waiting' => MatchStatus::Waiting->value,
        ]);

        return $statement->rowCount() === 1;
    }

    public function find(string $matchId): ?GameMatch
    {
        return $this->fetchMatch($matchId, forUpdate: false);
    }

    /**
     * Both reads happen in one transaction, so a move landing between them
     * cannot hand back a match and a move list that disagree.
     */
    public function snapshot(string $matchId, ?callable $refresh = null): ?MatchSnapshot
    {
        return $this->transactionally(function () use ($matchId, $refresh): ?MatchSnapshot {
            $match = $this->findForUpdate($matchId);

            if ($match === null) {
                return null;
            }

            $snapshot = new MatchSnapshot($match, $this->movesFor($matchId));

            return $refresh === null ? $snapshot : $refresh($snapshot);
        });
    }

    /**
     * The match as it stands, with the row held until the transaction ends.
     * Everything a move depends on is read through here, so two browsers
     * submitting for the same turn arrive one at a time and the second one sees
     * what the first one left behind.
     */
    public function findForUpdate(string $matchId): ?GameMatch
    {
        return $this->fetchMatch($matchId, forUpdate: true);
    }

    public function appendMove(string $matchId, int $moveNumber, PlayedMove $played, DateTimeImmutable $at): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO moves (match_id, move_number, uci, san, created_at)
             VALUES (:match_id, :move_number, :uci, :san, :created_at)',
        );

        $statement->execute([
            'match_id' => $matchId,
            'move_number' => $moveNumber,
            'uci' => $played->move->uci,
            'san' => $played->san,
            'created_at' => $at->format('Y-m-d H:i:s.v'),
        ]);
    }

    public function updateAfterMove(GameMatch $match): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE matches
                SET fen = :fen,
                    status = :status,
                    white_remaining_ms = :white_remaining_ms,
                    black_remaining_ms = :black_remaining_ms,
                    turn_started_at = :turn_started_at,
                    result_winner_color = :result_winner_color,
                    result_reason = :result_reason,
                    draw_offer_color = :draw_offer_color
              WHERE id = :id',
        );
        $statement->execute([
            'fen' => $match->fen,
            'status' => $match->status->value,
            'white_remaining_ms' => $match->whiteRemainingMs,
            'black_remaining_ms' => $match->blackRemainingMs,
            'turn_started_at' => $match->turnStartedAt?->format('Y-m-d H:i:s.v'),
            'result_winner_color' => $match->resultWinner?->value,
            'result_reason' => $match->resultReason?->value,
            'draw_offer_color' => $match->drawOfferBy?->value,
            'id' => $match->id,
        ]);
    }

    public function saveClockState(GameMatch $match): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE matches
                SET status = :status,
                    white_remaining_ms = :white_remaining_ms,
                    black_remaining_ms = :black_remaining_ms,
                    turn_started_at = :turn_started_at,
                    result_winner_color = :result_winner_color,
                    result_reason = :result_reason,
                    draw_offer_color = :draw_offer_color
              WHERE id = :id',
        );
        $statement->execute([
            'status' => $match->status->value,
            'white_remaining_ms' => $match->whiteRemainingMs,
            'black_remaining_ms' => $match->blackRemainingMs,
            'turn_started_at' => $match->turnStartedAt?->format('Y-m-d H:i:s.v'),
            'result_winner_color' => $match->resultWinner?->value,
            'result_reason' => $match->resultReason?->value,
            'draw_offer_color' => $match->drawOfferBy?->value,
            'id' => $match->id,
        ]);
    }

    /**
     * Marks a challenge abandoned only while it is still waiting and empty, so
     * a joiner who sat down in the same moment is not overwritten.
     */
    public function abandonIfStillWaiting(string $matchId): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE matches
                SET status = :abandoned
              WHERE id = :id AND status = :waiting AND joiner_token_hash IS NULL',
        );
        $statement->execute([
            'abandoned' => MatchStatus::Abandoned->value,
            'id' => $matchId,
            'waiting' => MatchStatus::Waiting->value,
        ]);

        return $statement->rowCount() === 1;
    }

    public function saveDrawOffer(GameMatch $match): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE matches SET draw_offer_color = :draw_offer_color WHERE id = :id',
        );
        $statement->execute([
            'draw_offer_color' => $match->drawOfferBy?->value,
            'id' => $match->id,
        ]);
    }

    public function lastMoveNumber(string $matchId): int
    {
        $statement = $this->pdo->prepare('SELECT COALESCE(MAX(move_number), 0) FROM moves WHERE match_id = :match_id');
        $statement->execute(['match_id' => $matchId]);

        return (int) $statement->fetchColumn();
    }

    public function movesFor(string $matchId): MoveLog
    {
        $statement = $this->pdo->prepare(
            'SELECT move_number, uci, san FROM moves WHERE match_id = :match_id ORDER BY move_number',
        );
        $statement->execute(['match_id' => $matchId]);

        return new MoveLog(array_map(
            static fn (array $row): RecordedMove => RecordedMove::fromRow($row),
            $statement->fetchAll(),
        ));
    }

    public function lastDrawEventNumber(string $matchId): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COALESCE(MAX(event_number), 0) FROM draw_events WHERE match_id = :match_id',
        );
        $statement->execute(['match_id' => $matchId]);

        return (int) $statement->fetchColumn();
    }

    public function appendDrawEvent(
        string $matchId,
        int $eventNumber,
        DrawEventKind $kind,
        Color $by,
        int $afterMoveNumber,
        DateTimeImmutable $at,
    ): void {
        $statement = $this->pdo->prepare(
            'INSERT INTO draw_events
                (match_id, event_number, kind, by_color, after_move_number, created_at)
             VALUES
                (:match_id, :event_number, :kind, :by_color, :after_move_number, :created_at)',
        );

        $statement->execute([
            'match_id' => $matchId,
            'event_number' => $eventNumber,
            'kind' => $kind->value,
            'by_color' => $by->value,
            'after_move_number' => $afterMoveNumber,
            'created_at' => $at->format('Y-m-d H:i:s.v'),
        ]);
    }

    public function drawEventsFor(string $matchId): DrawEventLog
    {
        $statement = $this->pdo->prepare(
            'SELECT event_number, kind, by_color, after_move_number
               FROM draw_events
              WHERE match_id = :match_id
              ORDER BY event_number',
        );
        $statement->execute(['match_id' => $matchId]);

        return new DrawEventLog(array_map(
            static fn (array $row): RecordedDrawEvent => RecordedDrawEvent::fromRow($row),
            $statement->fetchAll(),
        ));
    }

    /**
     * Runs the given work in one transaction, so a ply and the position it
     * produces are either both stored or neither is.
     *
     * @template T
     * @param callable(): T $work
     * @return T
     */
    public function transactionally(callable $work): mixed
    {
        $this->pdo->beginTransaction();

        try {
            $result = $work();
        } catch (Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }

        $this->pdo->commit();

        return $result;
    }

    private function fetchMatch(string $matchId, bool $forUpdate): ?GameMatch
    {
        if (!MatchId::isWellFormed($matchId)) {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT * FROM matches WHERE id = :id' . ($forUpdate ? ' FOR UPDATE' : ''),
        );
        $statement->execute(['id' => $matchId]);
        $row = $statement->fetch();

        return is_array($row) ? GameMatch::fromRow($row) : null;
    }
}
