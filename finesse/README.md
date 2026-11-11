# Floating Lyrics Cards 🎵

A Python + Pygame animation that displays **8 lyric cards** floating
upward with a typewriter effect, alternating black-and-white styles, and
ending with an animated binary dinosaur.

## Lyrics

1.  When I'm walkin' with you
2.  I watch the whole room change
3.  Baby, that's what you do
4.  No, my baby, don't play
5.  Blame it on my confidence
6.  Oh, blame it on your measurements
7.  Shut that shit down on sight
8.  That's right

After the final lyric, the Binary Dinosaur appears.

## Features

-   Floating cards with typewriter text animation
-   Alternating black-and-white card designs
-   Random horizontal positions
-   Animated upward movement
-   Binary Dinosaur ending
-   Press `Esc` to exit

## Requirements

-   Python 3.13
-   Pygame
-   Windows recommended for desktop transparency

## Run the project

Install Pygame:

``` powershell
py -3.13 -m pip install pygame
```

Open the project folder in VS Code, then run:

``` powershell
py -3.13 song.py
```

## Customize

Edit these settings near the top of `song.py`:

-   `LYRICS` --- change the displayed lines.
-   `LINE_DELAYS_SECONDS` --- change the delay between cards.
-   `SPEED_MULTIPLIER` --- adjust overall animation speed.
-   `TYPE_SPEEDS_MS` --- adjust the typewriter effect.

**Note:** This version does not play an MP3 or synchronize lyrics with
audio. The cards appear according to programmed delays.

------------------------------------------------------------------------

**Built with Python and Pygame.**
