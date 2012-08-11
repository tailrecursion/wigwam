<?php

/**
 * Returns a string representation of $n with ordinal suffix appended.
 */
function ordinal($n) {
  return date(
    "{$n}S", mktime(1,1,1,1,( (($n>=10)+($n>=20)+($n==0))*10 + $n%10) ));
}
