import React from 'react';
import { Outlet, Link } from 'react-router-dom';
import { ToastContainer } from 'react-toastify';
import 'react-toastify/dist/ReactToastify.css';

const PatientLayout = () => {
    return (
        <div className="min-h-screen bg-gray-100">
            <nav className="bg-white shadow-sm">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex justify-between h-16">
                        <div className="flex">
                            <div className="flex-shrink-0 flex items-center">
                                <Link to="/patient/dashboard" className="text-xl font-bold text-blue-600">
                                    PD Monitoring System
                                </Link>
                            </div>
                        </div>
                        <div className="flex items-center">
                            <Link 
                                to="/patient/dashboard" 
                                className="px-3 py-2 rounded-md text-sm font-medium text-gray-700 hover:text-blue-600"
                            >
                                Dashboard
                            </Link>
                        </div>
                    </div>
                </div>
            </nav>
            
            <main className="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
                <Outlet />
            </main>
            
            <ToastContainer 
                position="top-right"
                autoClose={5000}
                hideProgressBar={false}
                newestOnTop={false}
                closeOnClick
                rtl={false}
                pauseOnFocusLoss
                draggable
                pauseOnHover
            />
        </div>
    );
};

export default PatientLayout;