<?php


namespace App\Bot\Bank\Text\Chain;


use App\Bank;
use App\Bot\Bank\BankRelated;
use App\Chain;
use App\Word;
use Exception;
use Illuminate\Support\Facades\DB;

class ChainManager extends BankRelated {

    const SENTENCE_END = [
        "!",
        "?!",
        "?",
        ".",
        "..."
    ];
    const SPACE_DELIMITER = " ";
    const END_OF_SENTENCE = "";
    const NEW_SENTENCE = "";

    /**
     * Default learn of text (delimiter = space)
     * @param string $sourceText
     */
    public function learnText(string $sourceText){
        // TODO: merge pattern with self::SENTENCE_END
        $sentences = preg_split('/(?<=[.?!])\s+/', $sourceText, -1, PREG_SPLIT_NO_EMPTY);

        foreach($sentences as $sentence)
            $this->learnSentence($sentence, self::SPACE_DELIMITER);
    }

    /**
     * Learn the string with custom delimiter
     * @param string $sourceText
     * @param string $delimiter
     */
    public function learnSentence(string $sourceText, string $delimiter = self::SPACE_DELIMITER){
        $sourceTextAsArray = explode($delimiter, $sourceText);

        $this->learnFromArray($sourceTextAsArray);
    }

    /**
     * Learn the data from array
     * @param array $source
     */
    public function learnFromArray(array $source){

        // TODO: optimize SQL request

        $count = count($source);
        for($i = 0; $i < $count; $i++){
            $currentWord = $source[$i] ?? "";
            $nextWord = $source[$i + 1] ?? "";

//            $regexp = "/^(.*[^.!?])([.!?]|!{3}|\?!|!\?)$/i";
            $regexp = "/^(" . preg_quote(implode("|", self::SENTENCE_END)) . ")$/i";
            preg_match($regexp, $currentWord, $currentWordMatches);
            preg_match($regexp, $nextWord, $nextWordMatches);

            switch($i){
                // Define the end of the sentence
                case $count - 1:
                    $this->learn($currentWordMatches[1] ?? $currentWord, $currentWordMatches[2] ?? "");
                    continue 2;
                    break;

                // Define the start of the sentence
                case 0:
                    $this->learn("", $currentWordMatches[1] ?? $currentWord);
                    break;
            }
            // Learn the pair
            $this->learn($currentWordMatches[1] ?? $currentWord, $nextWordMatches[1] ?? $nextWord);
        }
    }

    /**
     * Learn by one pair
     * @param string $target
     * @param string $next
     */
    public function learn(string $target, string $next){
        foreach($this->targetBanks as $bank){
            /** @var Bank $bank */

            // TODO: log learning, "restore" function

            $targetModel = Word::query()->firstOrCreate([
                "bank_id" => $bank->id,
                "text" => $target
            ]);

            $nextModel = Word::query()->firstOrCreate([
                "bank_id" => $bank->id,
                "text" => $next
            ]);

            Chain::query()->firstOrCreate([
                "bank_id" => $bank->id,
                "target" => $targetModel->id,
                "next" => $nextModel->id
            ]);
        }
    }


    /**
     * @return DraftChain
     * @throws Exception
     */
    public function pickRandomUnregisteredPair(){
        // TODO: SQL request optimization

        try {
            DB::statement("SET @rows = 0");
            DB::statement("SET @bank_ids = ?", [implode(",", $this->targetBanks)]);
            $pair = DB::select('
                SELECT
                    *, (@rows := @rows + 1) AS rows_count
                FROM
                    (
                        SELECT *, (@rows := @rows + 1) AS id
                        FROM
                        (
                            SELECT
                                id as target_id,
                                text as target_text
                            FROM
                                wordbank AS tbl
                            WHERE
                                FIND_IN_SET(tbl.bank_id, @bank_ids)
                            ORDER BY tbl.id
                        ) AS res1,
                        (
                            SELECT
                                id as next_id,
                                text as next_text
                            FROM
                                wordbank AS tbl
                            WHERE
                                FIND_IN_SET(tbl.bank_id, @bank_ids)
                            ORDER BY tbl.id
                        ) AS res2
                        WHERE
                            CONCAT(res1.target_id, " ", res2.next_id) NOT IN(SELECT CONCAT(target, " ", next) FROM markov_chain)
                    ) unexisted_pairs
                
                WHERE
                    unexisted_pairs.id >= (SELECT RAND() * MAX(@rows))
                ORDER BY unexisted_pairs.next_id
                LIMIT 1
            ');
            if(count($pair) <= 0 || !isset($pair[0]) || !isset($pair[0]->target_id) || !isset($pair[0]->next_id))
                return null;

            return new DraftChain((int)$pair[0]->target_id, (int)$pair[0]->next_id);
        } catch (Exception $e) {
            throw $e;
        }
    }


}