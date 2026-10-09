<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Original disposals schema uses disposal_action + remarks, no new enum table. */
class Disposal_service
{
    public function choices()
    {
        return ['shred'=>'Shred','scratch'=>'Scratch','other'=>'Other'];
    }

    public function reason($post)
    {
        $value=trim((string)($post['disposal_reason']??''));
        if (!array_key_exists($value,$this->choices()))
            throw new DomainException('Choose Shred, Scratch or Other as disposal reason.');
        if ($value==='other') {
            $details=trim((string)($post['disposal_other']??''));
            if ($details==='' || mb_strlen($details)>250)
                throw new DomainException('Describe the other disposal reason (up to 250 characters).');
            return $details;
        }
        return $this->choices()[$value];
    }
}
