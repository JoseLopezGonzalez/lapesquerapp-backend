<?php

namespace App\Http\Controllers\v2\Traits;

use Beganovich\Snappdf\Snappdf;

trait HandlesChromiumConfig
{
    /**
     * Configure Chromium/Chrome path and arguments for PDF generation.
     *
     * @param Snappdf $snappdf
     * @param array $additionalArguments Additional arguments to add (optional)
     * @return void
     */
    protected function configureChromium(Snappdf $snappdf, array $additionalArguments = []): void
    {
        // Set Chromium path from configuration
        $chromiumPath = config('pdf.chromium.path', '/usr/bin/google-chrome');
        $snappdf->setChromiumPath($chromiumPath);

        // Apply margins from configuration
        $margins = config('pdf.chromium.margins', []);
        if (isset($margins['top'])) {
            $snappdf->addChromiumArguments('--margin-top=' . $margins['top']);
        }
        if (isset($margins['right'])) {
            $snappdf->addChromiumArguments('--margin-right=' . $margins['right']);
        }
        if (isset($margins['bottom'])) {
            $snappdf->addChromiumArguments('--margin-bottom=' . $margins['bottom']);
        }
        if (isset($margins['left'])) {
            $snappdf->addChromiumArguments('--margin-left=' . $margins['left']);
        }

        // Apply default arguments from configuration
        $defaultArguments = config('pdf.chromium.arguments', []);
        foreach ($defaultArguments as $argument) {
            $snappdf->addChromiumArguments($argument);
        }

        // Apply additional arguments if provided
        foreach ($additionalArguments as $argument) {
            $snappdf->addChromiumArguments($argument);
        }
    }
}

