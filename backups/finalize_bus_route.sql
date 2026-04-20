USE pare;

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE stations;
SET FOREIGN_KEY_CHECKS = 1;

-- Cabaantuan Cabanatuan Central Transport Terminal (Zulueta)
-- Exiting via Melencio St / Burgos Ave, bypassing SM Megacenter and Public Market
-- Merging into Maharlika Highway at Sangitan Junction
-- Branching right at Pinagpanaan Junction
-- Straight through Llanera
-- Terminating precisely at Rizal New Public Market (Poblacion Sur)

INSERT INTO stations (station_name, km_marker, latitude, longitude, is_terminal, sort_order) VALUES
('Cabanatuan Central Transport Terminal (Zulueta)', 1.00,  15.4724, 120.9690, 1, 1),
('Melencio St (SM Megacenter)',                 2.00,  15.4878, 120.9679, 0, 2),
('Burgos Ave (Cabanatuan Public Market)',       3.00,  15.4895, 120.9690, 0, 3),
('Sangitan Junction',                           4.00,  15.4920, 120.9711, 0, 4),
('Valdefuente (Bridge)',                        5.00,  15.5066, 120.9758, 0, 5),
('Highway Marker KM 6',                         6.00,  15.5120, 120.9710, 0, 6),
('Mayapyap',                                    7.00,  15.5218, 120.9568, 0, 7),
('Highway Marker KM 8',                         8.00,  15.5280, 120.9510, 0, 8),
('Caalibangbangan',                             9.00,  15.5346, 120.9468, 0, 9),
('Highway Marker KM 10',                       10.00,  15.5400, 120.9490, 0, 10),
('San Miguel Munti',                           11.00,  15.5498, 120.9524, 0, 11),
('Highway Marker KM 12',                       12.00,  15.5560, 120.9562, 0, 12),
('Pinagpanaan Junction (Right Turn)',          13.00,  15.5660, 120.9620, 0, 13),
('Highway Marker KM 14',                       14.00,  15.5725, 120.9672, 0, 14),
('Baluga / Sto. Niño',                         15.00,  15.5820, 120.9742, 0, 15),
('Highway Marker KM 16',                       16.00,  15.5900, 120.9820, 0, 16),
('Highway Marker KM 17',                       17.00,  15.5950, 120.9860, 0, 17),
('Homestead 1',                                18.00,  15.5998, 120.9912, 0, 18),
('Homestead 2',                                19.00,  15.6065, 120.9988, 0, 19),
('San Ricardo / Casile',                       20.00,  15.6132, 121.0058, 0, 20),
('Highway Marker KM 21',                       21.00,  15.6200, 121.0130, 0, 21),
('Highway Marker KM 22',                       22.00,  15.6250, 121.0160, 0, 22),
('Highway Marker KM 23',                       23.00,  15.6300, 121.0200, 0, 23),
('Llanera Municipal Hall / Market Area',       24.00,  15.6350, 121.0250, 0, 24),
('Luna',                                       25.00,  15.6395, 121.0295, 0, 25),
('Mataas na Kahoy',                            26.00,  15.6452, 121.0348, 0, 26),
('Ricarte',                                    27.00,  15.6515, 121.0402, 0, 27),
('Mabini (Entering Rizal)',                    28.00,  15.6578, 121.0458, 0, 28),
('Inanama',                                    29.00,  15.6638, 121.0512, 0, 29),
('Bicos',                                      30.00,  15.6698, 121.0568, 0, 30),
('Highway Marker KM 31',                       31.00,  15.6755, 121.0620, 0, 31),
('Mangahan',                                   32.00,  15.6810, 121.0672, 0, 32),
('Highway Marker KM 33',                       33.00,  15.6865, 121.0718, 0, 33),
('Aglipay',                                    34.00,  15.6918, 121.0762, 0, 34),
('Del Pilar',                                  35.00,  15.6988, 121.0830, 0, 35),
('Highway Marker KM 36',                       36.00,  15.7040, 121.0890, 0, 36),
('Highway Marker KM 37',                       37.00,  15.7060, 121.0920, 0, 37),
('Highway Marker KM 38',                       38.00,  15.7080, 121.0970, 0, 38),
('Highway Marker KM 39',                       39.00,  15.7100, 121.1010, 0, 39),
('Rizal New Public Market (Pob. Sur)',         40.00,  15.7126, 121.1071, 1, 40);

UPDATE trips SET status = 'cancelled' WHERE status = 'active';
