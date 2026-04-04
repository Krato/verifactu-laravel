<?php

namespace Krato\Verifactu\Enums;

enum SubmissionStatus: string
{
    case Pending = 'pending';
    case Submitted = 'submitted';
    case Accepted = 'accepted';
    case AcceptedWithErrors = 'accepted_with_errors';
    case Rejected = 'rejected';
    case Failed = 'failed';
}
