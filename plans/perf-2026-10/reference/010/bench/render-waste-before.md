## R1. params-updated fan-out per config: listener registered? / upload bytes / html bytes rendered back
| config | filter | listens | upload B | html B |
|---|---|---|---|---|
| counts=1 bounds=0 | checkbox | yes | 1182 | 5666 |
| counts=1 bounds=0 | checkbox-adv(500) | yes | 24161 | 41659 |
| counts=1 bounds=0 | select | yes | 1001 | 1231 |
| counts=1 bounds=0 | radio | yes | 885 | 1538 |
| counts=1 bounds=0 | text | no | - | - |
| counts=1 bounds=0 | toggle | no | - | - |
| counts=1 bounds=0 | date | no | - | - |
| counts=1 bounds=0 | range | yes | 999 | 1206 |
| counts=1 bounds=0 | dual | yes | 1061 | 3205 |
| counts=1 bounds=0 | TOTAL | | 29289 | 54505 |
| counts=0 bounds=1 | checkbox | yes | 1585 | 5054 |
| counts=0 bounds=1 | checkbox-adv(500) | yes | 24564 | 42986 |
| counts=0 bounds=1 | select | yes | 1404 | 1000 |
| counts=0 bounds=1 | radio | yes | 1288 | 1384 |
| counts=0 bounds=1 | text | no | - | - |
| counts=0 bounds=1 | toggle | no | - | - |
| counts=0 bounds=1 | date | no | - | - |
| counts=0 bounds=1 | range | yes | 1402 | 1206 |
| counts=0 bounds=1 | dual | yes | 1464 | 3205 |
| counts=0 bounds=1 | TOTAL | | 31707 | 54835 |
| counts=0 bounds=0 | checkbox | yes | 1182 | 5054 |
| counts=0 bounds=0 | checkbox-adv(500) | yes | 24161 | 42986 |
| counts=0 bounds=0 | select | yes | 1001 | 1000 |
| counts=0 bounds=0 | radio | yes | 885 | 1384 |
| counts=0 bounds=0 | text | no | - | - |
| counts=0 bounds=0 | toggle | no | - | - |
| counts=0 bounds=0 | date | no | - | - |
| counts=0 bounds=0 | range | yes | 999 | 1206 |
| counts=0 bounds=0 | dual | yes | 1061 | 3205 |
| counts=0 bounds=0 | TOTAL | | 29289 | 54835 |
| counts=1 bounds=1 | checkbox | yes | 1585 | 5666 |
| counts=1 bounds=1 | checkbox-adv(500) | yes | 24564 | 41659 |
| counts=1 bounds=1 | select | yes | 1404 | 1231 |
| counts=1 bounds=1 | radio | yes | 1288 | 1538 |
| counts=1 bounds=1 | text | no | - | - |
| counts=1 bounds=1 | toggle | no | - | - |
| counts=1 bounds=1 | date | no | - | - |
| counts=1 bounds=1 | range | yes | 1402 | 1206 |
| counts=1 bounds=1 | dual | yes | 1464 | 3205 |
| counts=1 bounds=1 | TOTAL | | 31707 | 54505 |
## R2. html bytes rendered back per interaction (counts=1 bounds=1)
| component | interaction | response html B | response snapshot B | server ms |
|---|---|---|---|---|
| checkbox-adv(500) | params-updated (counts) | 41659 | 19415 | 5.6 |
| checkbox-adv(500) | set selected | 43159 | 20924 | 9.7 |
| checkbox-adv(500) | clear-all-filters | 43159 | 20915 | 1.9 |
| checkbox | set selected | 5583 | 905 | 3.4 |
| checkbox | params-updated (counts) | 5662 | 876 | 3 |
| select | set selected | 1000 | 757 | 1.5 |
| select | params-updated (counts) | 1228 | 742 | 2.8 |
| radio | set selected | 1913 | 650 | 1.5 |
| radio | params-updated (counts) | 1536 | 641 | 1.4 |
| checkbox-plain(500) | set selected | 309851 | 20916 | 5.8 |
| checkbox-plain(500) | params-updated (counts) | 347851 | 19416 | 6.8 |
## D4. clear-option for a foreign field: html bytes rendered by the non-owning filter
| filter | rendered? | html B |
|---|---|---|
| checkbox | yes | 5054 |
| checkbox-adv(500) | yes | 43159 |
| select | yes | 1000 |
| radio | yes | 1384 |
| text | yes | 594 |
| toggle | yes | 852 |
| date | yes | 1302 |
| range | yes | 1206 |
| dual | yes | 3205 |
| TOTAL | | 57756 |
