<?php

return function (string $path): void {
    // This ignored deployment hook may modify the generated YAML in place.
    // Keep product defaults in the public template and deployment-specific rules here.
    // Throw on invalid state so the subscription cache rebuild fails visibly.
};
