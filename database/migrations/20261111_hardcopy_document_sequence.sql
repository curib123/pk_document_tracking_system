-- Sequence belongs to each hardcopy document, not to its Location.
-- It is one-to-many: several hardcopy documents may share the same Sequence, so no
-- unique key is added. Documents saved without a Sequence take the next number.
-- This gives existing hardcopies without a Sequence a number in registration order,
-- continuing after the highest numeric Sequence already in use. Existing values are kept.

SET @next_sequence := (
    SELECT COALESCE(MAX(CAST(sequence_number AS UNSIGNED)), 0)
    FROM hardcopy_documents
    WHERE sequence_number REGEXP '^[0-9]+$'
);

UPDATE hardcopy_documents d
JOIN (
    SELECT id, (@next_sequence := @next_sequence + 1) AS new_sequence
    FROM (
        SELECT id FROM hardcopy_documents
        WHERE sequence_number IS NULL OR TRIM(sequence_number) = ''
        ORDER BY id
    ) missing
) numbered ON numbered.id = d.id
SET d.sequence_number = CAST(CAST(numbered.new_sequence AS UNSIGNED) AS CHAR);
