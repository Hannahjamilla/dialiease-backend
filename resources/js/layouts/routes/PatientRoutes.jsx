import React from 'react';
import { Routes, Route } from 'react-router-dom';
import PatientLayout from '../layouts/PatientLayout';
import PatientDashboard from '../components/patient/PatientDashboard';
import TreatmentStart from '../components/patient/TreatmentStart';
import TreatmentEnd from '../components/patient/TreatmentEnd';

const PatientRoutes = () => {
    return (
        <Routes>
            <Route path="/" element={<PatientLayout />}>
                <Route path="dashboard" element={<PatientDashboard />} />
                <Route path="treatment/start" element={<TreatmentStart />} />
                <Route path="treatment/end" element={<TreatmentEnd />} />
            </Route>
        </Routes>
    );
};

export default PatientRoutes;