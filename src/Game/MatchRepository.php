<?php

declare(strict_types=1);

namespace Chess\Game;

use PDO;

final class MatchRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function insert(GameMatch $match): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO matches
                (id, status, time_control_seconds, creator_color, creator_token_hash, created_at)
             VALUES (:id, :status, :time_control_seconds, :creator_color, :creator_token_hash, :created_at)',
        );

        $statement->execute([
            'id' => $match->id,
            'status' => $match->status->value,
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
        if (!MatchId::isWellFormed($matchId)) {
            return null;
        }

        $statement = $this->pdo->prepare('SELECT * FROM matches WHERE id = :id');
        $statement->execute(['id' => $matchId]);
        $row = $statement->fetch();

        return is_array($row) ? GameMatch::fromRow($row) : null;
    }
}
