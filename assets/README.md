# SENTEC Visual Assets

This directory contains **reference copies** of the logo artwork and technical textures used by the SENTEC website. It exists so collaborators can inspect and replace the source imagery directly from the repository.

| File                         | Use in the site                                  | Runtime reference                                    |
| ---------------------------- | ------------------------------------------------ | ---------------------------------------------------- |
| `SENTECNEWWHITELOGO.webp`    | Exact original artwork in dark mode              | `/manus-storage/SENTECNEWWHITELOGO_2ba86ec2.webp`    |
| `SENTECNEWLOGO.webp`         | Exact blue/orange artwork in clinical light mode | `/manus-storage/SENTECNEWLOGO_b206fe12.webp`         |
| `sentec-hero-texture.png`    | Technical texture behind the home hero           | `/manus-storage/sentec-hero-texture_fbda6e33.png`    |
| `sentec-about-texture.png`   | About-section atmosphere                         | `/manus-storage/sentec-about-texture_326034d4.png`   |
| `sentec-project-texture.png` | Project-section atmosphere                       | `/manus-storage/sentec-project-texture_223a946e.png` |

> **Important:** The deployed web application intentionally continues to use the hosted `/manus-storage/...` paths above. Do not change runtime image references to this directory in the managed web project, because its asset workflow uses the hosted paths for reliable deployment.

To replace an image, first update the matching repository copy here. Then upload the new production asset through the established static-asset workflow and update the corresponding runtime path in `client/src/pages/Home.tsx` or `client/src/index.css`. This keeps the repository reviewable while preserving the existing deployment setup.
